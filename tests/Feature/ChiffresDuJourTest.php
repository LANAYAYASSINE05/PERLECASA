<?php

use App\Enums\CategorieCharge;
use App\Enums\ModePaiement;
use App\Enums\Periodicite;
use App\Enums\Role;
use App\Enums\TypeCompte;
use App\Enums\TypeDecaissement;
use App\Enums\TypeEncaissement;
use App\Filament\Widgets\ChiffresDuJour;
use App\Models\ChargeFixe;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Models\Fournisseur;
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
        'decaisse' => 30500.0, 'decaissements' => 2,
        'ventes' => 800000.0, 'nbVentes' => 1,
        'caisse' => 500.0, 'nbCaisse' => 1, 'caisseNonJustifiee' => 1,
        'chargesARegler' => 6000.0, 'nbCharges' => 1, 'nbRetards' => 0,
    ]);

    Livewire::test(ChiffresDuJour::class)
        ->assertSee('Encaissé aujourd’hui')->assertSee('200 000,00')
        ->assertSee('Solde du jour')->assertSee('169 500,00')
        ->assertSee('Ventes du jour')
        ->assertSee('dont 1 sans pièce')
        ->assertSee('Charges fixes à régler');
});

it('ne montre au caissier que sa caisse', function () {
    $caissier = utilisateur(Role::Caissier);
    $this->actingAs($caissier);

    expect(ChiffresDuJour::chiffres($caissier)['decaisse'])->toBe(500.0);

    Livewire::test(ChiffresDuJour::class)
        ->assertSee('Sorti de caisse aujourd’hui')
        ->assertSee('Caisse du jour')
        ->assertDontSee('Encaissé aujourd’hui')
        ->assertDontSee('Solde du jour')
        ->assertDontSee('Ventes du jour')
        ->assertDontSee('Charges fixes à régler')
        ->assertDontSee('30 500,00');
});

it('montre à l’associé les chiffres du jour sans caisse ni charges', function () {
    $this->actingAs(utilisateur(Role::Associe));

    Livewire::test(ChiffresDuJour::class)
        ->assertSee('Encaissé aujourd’hui')
        ->assertSee('Décaissé aujourd’hui')
        ->assertSee('Solde du jour')
        ->assertSee('Ventes du jour')
        ->assertDontSee('Caisse du jour')
        ->assertDontSee('Charges fixes à régler');
});

it('affiche les cartes du jour sur le tableau de bord de chaque rôle', function (Role $role) {
    $this->actingAs(utilisateur($role))->get('/')->assertOk()->assertSee('Aujourd’hui');
})->with([Role::Administrateur, Role::Comptable, Role::Caissier, Role::Associe]);
