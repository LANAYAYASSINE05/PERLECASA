<?php

use App\Enums\CategorieCharge;
use App\Enums\ModePaiement;
use App\Enums\Periodicite;
use App\Enums\Role;
use App\Enums\TypeCompte;
use App\Enums\TypeDecaissement;
use App\Enums\TypeEncaissement;
use App\Filament\Widgets\ChiffresDuJour;
use App\Filament\Widgets\ComptesTresorerie;
use App\Models\ChargeFixe;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Models\Fournisseur;
use App\Support\PeriodeTableau;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $this->actingAs(utilisateur(Role::Administrateur));
    $banque = compte(soldeInitial: 500000);
    $caisse = compte(TypeCompte::Caisse, 10000);

    $vente = vente(800000);
    Encaissement::create([
        'type' => TypeEncaissement::VenteAppartement, 'vente_id' => $vente->id, 'date_operation' => now(),
        'compte_id' => $banque->id, 'mode' => ModePaiement::Virement, 'montant' => 200000,
    ]);
    Encaissement::create([
        'type' => TypeEncaissement::VenteAppartement, 'vente_id' => $vente->id, 'date_operation' => now()->subDay(),
        'compte_id' => $banque->id, 'mode' => ModePaiement::Virement, 'montant' => 50000,
    ]);
    Decaissement::create([
        'type' => TypeDecaissement::Fournisseur, 'fournisseur_id' => Fournisseur::create(['raison_sociale' => 'Fournisseur du jour'])->id,
        'date_operation' => now(), 'compte_id' => $banque->id, 'mode' => ModePaiement::Virement, 'montant' => 30000,
    ]);
    Decaissement::create([
        'type' => TypeDecaissement::OperationCaisse, 'operateur' => 'Caissier', 'motif' => 'Fournitures',
        'date_operation' => now(), 'compte_id' => $caisse->id, 'mode' => ModePaiement::Especes, 'montant' => 500,
    ]);
    ChargeFixe::create(['libelle' => 'Loyer', 'categorie' => CategorieCharge::Loyer, 'montant' => 6000, 'periodicite' => Periodicite::Mensuelle]);
});

it('compte les mouvements du jour seulement', function () {
    expect(ChiffresDuJour::chiffres(auth()->user()))->toMatchArray([
        'encaisse' => 200000.0, 'encaissements' => 1,
        'decaisse' => 30500.0, 'decaissements' => 2, 'sansPiece' => 1,
        'resteAEncaisser' => 550000.0,
    ]);

    Livewire::test(ChiffresDuJour::class)
        ->assertSee('Encaissé aujourd’hui')->assertSee('200 000,00')
        ->assertSee('Solde du jour')->assertSee('169 500,00')
        ->assertSee('dont 1 sans pièce')
        ->assertSee('Reste à encaisser')->assertSee('550 000,00')
        ->assertDontSee('Ventes aujourd’hui')
        ->assertDontSee('Charges fixes à régler');
});

it('ne montre au caissier que sa caisse', function () {
    $caissier = utilisateur(Role::Caissier);
    $this->actingAs($caissier);

    expect(ChiffresDuJour::chiffres($caissier)['decaisse'])->toBe(500.0);

    Livewire::test(ChiffresDuJour::class)
        ->assertSee('Sorti de caisse aujourd’hui')
        ->assertSee('dont 1 sans pièce')
        ->assertDontSee('Encaissé aujourd’hui')
        ->assertDontSee('Solde du jour')
        ->assertDontSee('Reste à encaisser')
        ->assertDontSee('30 500,00');
});

it('montre à l’associé les quatre chiffres sans le détail de caisse', function () {
    $this->actingAs(utilisateur(Role::Associe));

    Livewire::test(ChiffresDuJour::class)
        ->assertSee('Encaissé aujourd’hui')
        ->assertSee('Décaissé aujourd’hui')
        ->assertSee('Solde du jour')
        ->assertSee('Reste à encaisser')
        ->assertDontSee('sans pièce');
});

it('filtre les cartes par période', function () {
    $admin = auth()->user();

    expect(ChiffresDuJour::chiffres($admin, PeriodeTableau::depuisFiltres(['periode' => 'hier']))['encaisse'])->toBe(50000.0)
        ->and(ChiffresDuJour::chiffres($admin, PeriodeTableau::depuisFiltres(['periode' => 'date', 'date' => now()->subDay()->toDateString()]))['encaisse'])->toBe(50000.0)
        ->and(PeriodeTableau::depuisFiltres(['periode' => 'date', 'date' => now()->addDay()->toDateString()])->cle)->toBe('aujourdhui')
        ->and(PeriodeTableau::depuisFiltres(['periode' => 'inconnue'])->cle)->toBe('aujourdhui');

    $mois = ChiffresDuJour::chiffres($admin, PeriodeTableau::depuisFiltres(['periode' => 'mois']));
    expect($mois['encaisse'])->toBe(now()->day > 1 ? 250000.0 : 200000.0);

    Livewire::test(ChiffresDuJour::class)
        ->assertSee('Période des cartes', false)
        ->assertSeeInOrder(['Aujourd’hui', 'Hier', 'Semaine', 'Mois', 'Date'])
        ->assertSee('Aujourd’hui ·')
        ->call('$set', 'periode', 'hier')
        ->assertSet('periode', 'hier')
        ->set('periode', 'hier')
        ->assertSee('Hier ·')->assertSee('Encaissé hier')->assertSee('50 000,00')
        ->assertSee('Solde du jour')
        ->set('periode', 'semaine')
        ->assertSee('Cette semaine ·')->assertSee('Solde de la période')
        ->set('periode', 'date')
        ->assertSet('date', today()->toDateString())
        ->set('date', now()->subDay()->toDateString())
        ->assertSee('Encaissé le '.now()->subDay()->format('d/m/Y'));

    expect(Livewire::test(ChiffresDuJour::class)->get('periode'))->toBe('date');
});

it('affiche les cartes du jour sur le tableau de bord de chaque rôle', function (Role $role) {
    $this->actingAs(utilisateur($role))->get('/')->assertOk()->assertSee('Aujourd’hui');
})->with([Role::Administrateur, Role::Comptable, Role::Caissier, Role::Associe]);

it('montre les soldes des comptes et la trésorerie', function () {
    Livewire::test(ComptesTresorerie::class)
        ->assertSee('Comptes et trésorerie')
        ->assertSee('Trésorerie disponible')
        ->assertSee('729 500,00')
        ->assertSee('Banque')
        ->assertSee('Caisse');

    $this->actingAs(utilisateur(Role::Caissier));
    expect(ComptesTresorerie::canView())->toBeFalse();

    $this->actingAs(utilisateur(Role::Associe));
    Livewire::test(ComptesTresorerie::class)->assertSee('Trésorerie disponible');
});
