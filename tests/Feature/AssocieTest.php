<?php

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Enums\TypeCompte;
use App\Enums\TypeDecaissement;
use App\Enums\TypeEncaissement;
use App\Filament\Pages\Associes;
use App\Filament\Resources\DecaissementResource\Pages\ManageDecaissements;
use App\Filament\Resources\EncaissementResource\Pages\ManageEncaissements;
use App\Filament\Widgets\BandeauAccueil;
use App\Filament\Widgets\MesApports;
use App\Filament\Widgets\MesRemboursements;
use App\Filament\Widgets\ResultatSimplifie;
use App\Filament\Widgets\ResumeInvestissements;
use App\Filament\Widgets\SyntheseProgrammes;
use App\Filament\Widgets\SyntheseTresorerie;
use App\Models\Compte;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Models\Fournisseur;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(fn () => Filament::setCurrentPanel(Filament::getPanel('app')));

function apport(User $associe, float $montant = 100000, ?Compte $compte = null): Encaissement
{
    test()->actingAs(utilisateur(Role::Administrateur));

    return Encaissement::create([
        'type' => TypeEncaissement::ApportAssocie, 'associe_id' => $associe->id, 'date_operation' => now(),
        'compte_id' => ($compte ?? compte())->id, 'mode' => ModePaiement::Virement, 'montant' => $montant,
    ]);
}

function remboursement(User $associe, float $montant, Compte $compte): Decaissement
{
    test()->actingAs(utilisateur(Role::Administrateur));

    return Decaissement::create([
        'type' => TypeDecaissement::RemboursementAssocie, 'associe_id' => $associe->id, 'date_operation' => now(),
        'compte_id' => $compte->id, 'mode' => ModePaiement::Virement, 'montant' => $montant,
    ]);
}

it('laisse l’administrateur enregistrer un apport et un remboursement qui font bouger le compte', function () {
    $associe = utilisateur(Role::Associe);
    $compte = compte(soldeInitial: 0);
    $this->actingAs(utilisateur(Role::Administrateur));

    Livewire::test(ManageEncaissements::class)
        ->callAction('create', [
            'type' => TypeEncaissement::ApportAssocie->value,
            'associe_id' => $associe->id,
            'date_operation' => now()->toDateString(),
            'compte_id' => $compte->id,
            'mode' => ModePaiement::Virement->value,
            'montant' => 200000,
        ])
        ->assertHasNoActionErrors();

    Livewire::test(ManageDecaissements::class)
        ->callAction('create', [
            'type' => TypeDecaissement::RemboursementAssocie->value,
            'associe_id' => $associe->id,
            'date_operation' => now()->toDateString(),
            'compte_id' => $compte->id,
            'mode' => ModePaiement::Virement->value,
            'montant' => 50000,
        ])
        ->assertHasNoActionErrors();

    expect($compte->solde())->toBe(150000.0)
        ->and($associe->totalApporte())->toBe(200000.0)
        ->and($associe->totalRembourse())->toBe(50000.0)
        ->and($associe->netInvesti())->toBe(150000.0)
        ->and($associe->partDuCapital())->toBe(100.0);
});

it('exige un associé pour un apport', function () {
    $this->actingAs(utilisateur(Role::Administrateur));

    Livewire::test(ManageEncaissements::class)
        ->callAction('create', [
            'type' => TypeEncaissement::ApportAssocie->value,
            'associe_id' => utilisateur(Role::Comptable)->id,
            'date_operation' => now()->toDateString(),
            'compte_id' => compte()->id,
            'mode' => ModePaiement::Virement->value,
            'montant' => 1000,
        ])
        ->assertHasActionErrors(['associe_id']);

    expect(Encaissement::count())->toBe(0);
});

it('interdit au comptable de créer, modifier ou supprimer un apport', function () {
    $associe = utilisateur(Role::Associe);
    $apport = apport($associe, 1000);
    $comptable = utilisateur(Role::Comptable);
    $this->actingAs($comptable);

    expect($comptable->can('view', $apport))->toBeTrue()
        ->and($comptable->can('update', $apport))->toBeFalse()
        ->and($comptable->can('delete', $apport))->toBeFalse();

    Livewire::test(ManageEncaissements::class)
        ->assertCanSeeTableRecords([$apport])
        ->assertTableActionHidden('edit', $apport)
        ->assertTableActionHidden('delete', $apport)
        ->call('mountTableAction', 'delete', (string) $apport->id)
        ->call('callMountedTableAction');

    expect($apport->fresh())->not->toBeNull();

    expect(fn () => Encaissement::create([
        'type' => TypeEncaissement::ApportAssocie, 'associe_id' => $associe->id, 'date_operation' => now(),
        'compte_id' => compte()->id, 'mode' => ModePaiement::Virement, 'montant' => 500,
    ]))->toThrow(ValidationException::class);

    expect(fn () => Decaissement::create([
        'type' => TypeDecaissement::RemboursementAssocie, 'associe_id' => $associe->id, 'date_operation' => now(),
        'compte_id' => compte()->id, 'mode' => ModePaiement::Virement, 'montant' => 500,
    ]))->toThrow(ValidationException::class);
});

it('montre à l’associé ses seuls investissements', function () {
    $associe = utilisateur(Role::Associe);
    $autre = utilisateur(Role::Associe);
    $compte = compte(soldeInitial: 0);
    $sien = apport($associe, 300000, $compte);
    $rembourse = remboursement($associe, 100000, $compte);
    $etranger = apport($autre, 600000, $compte);

    $this->actingAs($associe);

    expect($associe->netInvesti())->toBe(200000.0)
        ->and($associe->partDuCapital())->toBe(25.0);

    $this->get('/mes-investissements')->assertOk()->assertSee('Mon investissement');

    Livewire::test(ResumeInvestissements::class)->assertSee('25,00 %');
    Livewire::test(MesApports::class)
        ->assertCanSeeTableRecords([$sien])
        ->assertCanNotSeeTableRecords([$etranger]);
    Livewire::test(MesRemboursements::class)->assertCanSeeTableRecords([$rembourse]);

    $this->get("/encaissements/{$sien->id}/recu")->assertOk();
    $this->get("/encaissements/{$etranger->id}/recu")->assertForbidden();
    $this->get("/associes/{$associe->id}/releve")->assertOk();
    $this->get("/associes/{$autre->id}/releve")->assertForbidden();
});

it('range l’espace associé en quatre pages de la barre latérale', function () {
    $this->actingAs(utilisateur(Role::Associe))->get('/')
        ->assertOk()
        ->assertSeeInOrder(['/mes-investissements', '/espace-associe/societe', '/espace-associe/vie-sociale', '/espace-associe/demandes'], false)
        ->assertDontSee('/espace-associe/fiche-programme', false)
        ->assertDontSee('/encaissements"', false);
});

it('ne montre pas l’espace associé dans la barre latérale du comptable', function () {
    $this->actingAs(utilisateur(Role::Comptable))->get('/')
        ->assertOk()
        ->assertDontSee('/espace-associe/', false)
        ->assertDontSee('/mes-investissements', false);
});

it('ferme à l’associé les écrans métier et le récapitulatif', function (string $url) {
    $this->actingAs(utilisateur(Role::Associe))->get($url)->assertForbidden();
})->with(['/ventes', '/encaissements', '/decaissements', '/comptes', '/utilisateurs', '/associes']);

it('donne à l’administrateur le récapitulatif et les relevés de tous les associés', function () {
    $associe = utilisateur(Role::Associe);
    apport($associe, 400000);

    $this->actingAs(utilisateur(Role::Administrateur));

    Livewire::test(Associes::class)
        ->assertCanSeeTableRecords([$associe])
        ->assertSee('100,00 %');

    $this->get("/associes/{$associe->id}/releve")->assertOk();
    $this->get('/associes/'.utilisateur(Role::Comptable)->id.'/releve')->assertNotFound();
});

it('notifie l’associé de ses seuls apports et remboursements', function () {
    $associe = utilisateur(Role::Associe);
    $autre = utilisateur(Role::Associe);
    $comptable = utilisateur(Role::Comptable);
    $compte = compte(soldeInitial: 0);

    $sien = apport($associe, 1000, $compte);
    apport($autre, 2000, $compte);
    remboursement($associe, 500, $compte);
    $sien->delete();

    expect($associe->notifications()->count())->toBe(3)
        ->and($autre->notifications()->count())->toBe(1)
        ->and($associe->notifications()->get()->pluck('data.title'))->toContain("Apport {$sien->numero} supprimé");

    $this->actingAs(utilisateur(Role::Caissier));
    Decaissement::create([
        'type' => TypeDecaissement::OperationCaisse, 'operateur' => 'Caissier', 'motif' => 'Test',
        'date_operation' => now(), 'compte_id' => compte(TypeCompte::Caisse)->id, 'mode' => ModePaiement::Especes, 'montant' => 100,
    ]);

    expect($associe->notifications()->count())->toBe(3)
        ->and($comptable->notifications()->count())->toBeGreaterThan(0);
});

it('montre à l’associé la trésorerie, les programmes et le résultat, sans les ouvrir au comptable', function () {
    $associe = utilisateur(Role::Associe);
    $banque = compte(soldeInitial: 0);
    apport($associe, 400000, $banque);
    apport(utilisateur(Role::Associe), 600000, $banque);

    $vente = vente(1000000);
    Encaissement::create([
        'type' => TypeEncaissement::VenteAppartement, 'vente_id' => $vente->id, 'date_operation' => now(),
        'compte_id' => $banque->id, 'mode' => ModePaiement::Virement, 'montant' => 300000,
    ]);
    Decaissement::create([
        'type' => TypeDecaissement::Fournisseur, 'fournisseur_id' => Fournisseur::create(['raison_sociale' => 'Fournisseur test'])->id,
        'date_operation' => now(), 'compte_id' => $banque->id, 'mode' => ModePaiement::Virement, 'montant' => 100000,
    ]);

    $this->actingAs($associe);

    expect(ResultatSimplifie::chiffres())->toBe([
        'ventes' => 300000.0, 'depenses' => 100000.0, 'resultat' => 200000.0, 'quotePart' => 80000.0,
    ]);

    Livewire::test(SyntheseTresorerie::class)->assertSee('1 200 000,00')->assertSee($banque->nom);
    Livewire::test(ResultatSimplifie::class)->assertSee('Votre quote-part');
    Livewire::test(SyntheseProgrammes::class)
        ->assertCanSeeTableRecords([$vente->appartement->programme])
        ->assertSee('700 000,00')
        ->assertDontSee($vente->client->nom.' '.$vente->client->prenom);

    Livewire::test(BandeauAccueil::class)
        ->assertSee('Espace associé')
        ->assertSee('Mon investissement')
        ->assertDontSee('Nouvel encaissement');

    foreach (['/ventes', '/encaissements', '/decaissements', '/comptes'] as $url) {
        $this->get($url)->assertForbidden();
    }

    $this->actingAs(utilisateur(Role::Comptable));

    expect(SyntheseTresorerie::canView())->toBeFalse()
        ->and(SyntheseProgrammes::canView())->toBeFalse()
        ->and(ResultatSimplifie::canView())->toBeFalse();
});
