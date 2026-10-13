<?php

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Enums\StatutDemande;
use App\Enums\TypeDemande;
use App\Enums\TypeEncaissement;
use App\Filament\Pages\CapitalAssocie;
use App\Filament\Pages\DemandesAssocie;
use App\Filament\Resources\DemandeAssocieResource\Pages\ManageDemandesAssocies;
use App\Filament\Widgets\GraphiqueCapital;
use App\Filament\Widgets\LotsProgramme;
use App\Filament\Widgets\ResumeStock;
use App\Models\DemandeAssocie;
use App\Models\Encaissement;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(fn () => Filament::setCurrentPanel(Filament::getPanel('app')));

const PAGES_ASSOCIE = [
    '/mes-investissements', '/espace-associe/capital', '/espace-associe/resultat', '/espace-associe/tresorerie',
    '/espace-associe/programmes', '/espace-associe/stock', '/espace-associe/demandes',
];

function apportDe(User $associe, float $montant): void
{
    test()->actingAs(utilisateur(Role::Administrateur));
    Encaissement::create([
        'type' => TypeEncaissement::ApportAssocie, 'associe_id' => $associe->id, 'date_operation' => now(),
        'compte_id' => compte()->id, 'mode' => ModePaiement::Virement, 'montant' => $montant,
    ]);
}

it('donne à l’associé sept pages, dans l’ordre, sans groupes', function () {
    $this->actingAs(utilisateur(Role::Associe))->get('/')
        ->assertOk()
        ->assertSeeInOrder(PAGES_ASSOCIE, false)
        ->assertDontSee('Vie sociale')
        ->assertDontSee('Programmes immobiliers');
});

it('affiche chaque page de l’associé', function (string $url) {
    vente();
    apportDe($associe = utilisateur(Role::Associe), 1000);

    $this->actingAs($associe)->get($url)->assertOk();
})->with(PAGES_ASSOCIE);

it('a retiré documents, agenda, actualités, profil, charges et fiche programme', function (string $url) {
    $this->actingAs(utilisateur(Role::Associe))->get($url)->assertNotFound();
})->with(['/espace-associe/documents', '/espace-associe/agenda', '/espace-associe/actualites', '/espace-associe/profil',
    '/espace-associe/charges', '/espace-associe/fiche-programme', '/espace-associe/societe', '/espace-associe/vie-sociale']);

it('ferme les pages de l’associé aux autres rôles', function (string $url) {
    $this->actingAs(utilisateur(Role::Comptable))->get($url)->assertForbidden();
    $this->actingAs(utilisateur(Role::Administrateur))->get($url)->assertForbidden();
})->with(PAGES_ASSOCIE);

it('montre la part de chaque associé, avec les noms', function () {
    $associe = utilisateur(Role::Associe);
    $autre = utilisateur(Role::Associe);
    apportDe($associe, 250000);
    apportDe($autre, 750000);

    $this->actingAs($associe);

    expect(GraphiqueCapital::parts())->toBe([$autre->name => 75.0, $associe->name => 25.0]);
    Livewire::test(CapitalAssocie::class)
        ->assertCanSeeTableRecords([$associe, $autre])
        ->assertSee('75,00 %')
        ->assertSee('Vous')
        ->assertDontSee($autre->email);
});

it('montre le stock sans nommer les clients', function () {
    $vente = vente(1200000);
    $libre = appartement(900000);

    $this->actingAs(utilisateur(Role::Associe));

    expect(ResumeStock::chiffres())->toBe(['lots' => 2, 'disponibles' => 1, 'vendus' => 1, 'valeurStock' => 900000.0]);
    Livewire::test(LotsProgramme::class)
        ->assertCanSeeTableRecords([$libre, $vente->appartement])
        ->assertDontSee($vente->client->nom.' '.$vente->client->prenom);
});

it('fait circuler une demande de l’associé vers l’administrateur et sa réponse', function () {
    $admin = utilisateur(Role::Administrateur);
    $associe = utilisateur(Role::Associe);
    $autre = utilisateur(Role::Associe);

    $this->actingAs($associe);
    Livewire::test(DemandesAssocie::class)
        ->callAction('create', [
            'type' => TypeDemande::Remboursement->value, 'objet' => 'Retrait partiel',
            'montant' => 50000, 'message' => 'Je souhaite retirer une partie de mon apport.',
        ])
        ->assertHasNoActionErrors();

    $demande = DemandeAssocie::sole();
    expect($demande->associe_id)->toBe($associe->id)
        ->and($demande->statut)->toBe(StatutDemande::EnAttente)
        ->and($admin->notifications()->count())->toBe(1);

    $this->actingAs($autre);
    Livewire::test(DemandesAssocie::class)->assertCanNotSeeTableRecords([$demande]);
    expect($autre->can('view', $demande))->toBeFalse();
    $this->get('/demandes-associes')->assertForbidden();

    $this->actingAs($admin);
    Livewire::test(ManageDemandesAssocies::class)
        ->callTableAction('traiter', $demande, ['statut' => StatutDemande::Refusee->value, 'reponse' => 'Pas avant la clôture.'])
        ->assertHasNoTableActionErrors();

    expect($demande->refresh()->statut)->toBe(StatutDemande::Refusee)
        ->and($associe->notifications()->count())->toBe(1)
        ->and($autre->notifications()->count())->toBe(0);
});
