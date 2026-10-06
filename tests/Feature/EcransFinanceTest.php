<?php

use App\Enums\CategorieCharge;
use App\Enums\ModePaiement;
use App\Enums\Periodicite;
use App\Enums\Role;
use App\Enums\TypeCompte;
use App\Enums\TypeDecaissement;
use App\Enums\TypeEncaissement;
use App\Filament\Resources\ChargeFixeResource\Pages\ManageChargeFixes;
use App\Filament\Resources\DecaissementResource\Pages\ManageDecaissements;
use App\Filament\Resources\EncaissementResource\Pages\ManageEncaissements;
use App\Filament\Resources\VenteResource\Pages\ManageVentes;
use App\Filament\Widgets\BandeauAccueil;
use App\Filament\Widgets\CaisseNonJustifiee;
use App\Filament\Widgets\ChargesEnRetard;
use App\Filament\Widgets\ChiffresFinance;
use App\Filament\Widgets\FluxMensuels;
use App\Models\ChargeFixe;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Models\Fournisseur;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('enregistre un virement reçu depuis l’écran des encaissements', function () {
    $this->actingAs(utilisateur());
    $banque = compte();

    Livewire::test(ManageEncaissements::class)
        ->callAction('create', [
            'type' => TypeEncaissement::VirementRecu->value,
            'emetteur' => 'Étude notariale',
            'date_operation' => now()->toDateString(),
            'compte_id' => $banque->id,
            'mode' => ModePaiement::Virement->value,
            'montant' => 45000,
        ])
        ->assertHasNoActionErrors();

    expect(Encaissement::sole())
        ->emetteur->toBe('Étude notariale')
        ->numero->toStartWith('ENC-');
});

it('affiche l’erreur de reste dû sur le champ montant', function () {
    $this->actingAs(utilisateur());
    $vente = vente(200000);

    Livewire::test(ManageEncaissements::class)
        ->callAction('create', [
            'type' => TypeEncaissement::VenteAppartement->value,
            'vente_id' => $vente->id,
            'date_operation' => now()->toDateString(),
            'compte_id' => compte()->id,
            'mode' => ModePaiement::Virement->value,
            'montant' => 250000,
        ])
        ->assertHasActionErrors(['montant']);

    expect(Encaissement::count())->toBe(0);
});

it('encaisse une tranche depuis la vente', function () {
    $this->actingAs(utilisateur());
    $vente = vente(300000);
    $banque = compte();

    Livewire::test(ManageVentes::class)
        ->callTableAction('encaisser', $vente, [
            'date_operation' => now()->toDateString(),
            'compte_id' => $banque->id,
            'mode' => ModePaiement::Cheque->value,
            'montant' => 90000,
        ])
        ->assertHasNoTableActionErrors();

    expect($vente->resteDu())->toBe(210000.0);
});

it('paie la prochaine période d’une charge fixe', function () {
    $this->actingAs(utilisateur());
    $banque = compte();
    $charge = ChargeFixe::create([
        'libelle' => 'Internet', 'categorie' => CategorieCharge::Telecom, 'montant' => 1800, 'periodicite' => Periodicite::Mensuelle,
    ]);

    Livewire::test(ManageChargeFixes::class)
        ->callTableAction('payer', $charge, ['compte_id' => $banque->id])
        ->assertHasNoTableActionErrors();

    expect(Decaissement::sole())
        ->type->toBe(TypeDecaissement::ChargeFixe)
        ->periode->toBe(now()->format('Y-m'))
        ->and($charge->prochainePeriode())->toBe(now()->addMonthNoOverflow()->format('Y-m'));
});

it('limite l’opérateur de caisse aux opérations de caisse', function () {
    $this->actingAs(utilisateur(Role::Caissier));
    $caisse = compte(TypeCompte::Caisse, 3000);
    $banque = compte();
    $fournisseur = Fournisseur::create(['raison_sociale' => 'Visible seulement du comptable']);
    Decaissement::create([
        'type' => TypeDecaissement::Fournisseur, 'fournisseur_id' => $fournisseur->id, 'date_operation' => now(),
        'compte_id' => $banque->id, 'mode' => ModePaiement::Virement, 'montant' => 500,
    ]);

    Livewire::test(ManageDecaissements::class)
        ->callAction('create', [
            'operateur' => 'Youssef',
            'motif' => 'Fournitures',
            'date_operation' => now()->toDateString(),
            'compte_id' => $caisse->id,
            'mode' => ModePaiement::Especes->value,
            'montant' => 300,
        ])
        ->assertHasNoActionErrors()
        ->assertCanNotSeeTableRecords(Decaissement::where('type', TypeDecaissement::Fournisseur)->get());

    expect(Decaissement::where('type', TypeDecaissement::OperationCaisse)->sole())
        ->compte_id->toBe($caisse->id)
        ->justifie->toBeFalse();
});

it('justifie une opération de caisse avec une pièce', function () {
    Storage::fake('local');
    $this->actingAs(utilisateur(Role::Caissier));
    $operation = Decaissement::create([
        'type' => TypeDecaissement::OperationCaisse, 'operateur' => 'Youssef', 'motif' => 'Taxi', 'date_operation' => now(),
        'compte_id' => compte(TypeCompte::Caisse, 1000)->id, 'mode' => ModePaiement::Especes, 'montant' => 120,
    ]);

    Livewire::test(ManageDecaissements::class)
        ->callTableAction('justifier', $operation, [
            'piece_justificative' => UploadedFile::fake()->createWithContent('recu.pdf', "%PDF-1.4\n%%EOF\n"),
        ])
        ->assertHasNoTableActionErrors();

    expect($operation->fresh()->justifie)->toBeTrue();
    Storage::disk('local')->assertExists($operation->fresh()->piece_justificative);

    $this->get(route('decaissements.piece', $operation))->assertOk();
});

it('affiche le tableau de bord avec les données de démonstration', function () {
    config(['app.demo.password' => 'demo-test-1234']);
    Storage::fake('local');
    $this->seed(DemoSeeder::class);

    $this->actingAs(utilisateur(Role::Administrateur))->get('/')->assertOk();

    Livewire::test(BandeauAccueil::class)
        ->assertSee('Service Finance')
        ->assertSee('Nouvel encaissement');
    Livewire::test(ChiffresFinance::class)
        ->assertSee('Caisse non justifiée')
        ->assertSee('5 opération(s) sans pièce');
    Livewire::test(CaisseNonJustifiee::class)->assertCanSeeTableRecords(Decaissement::caisseNonJustifiee()->get());
    Livewire::test(FluxMensuels::class)->assertOk();
    Livewire::test(ChargesEnRetard::class)->assertOk();
});
