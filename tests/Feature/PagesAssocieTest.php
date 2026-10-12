<?php

use App\Enums\CategorieCharge;
use App\Enums\ModePaiement;
use App\Enums\Periodicite;
use App\Enums\Role;
use App\Enums\TypeEncaissement;
use App\Enums\TypeEvenement;
use App\Filament\Pages\AgendaAssocie;
use App\Filament\Pages\CapitalAssocie;
use App\Filament\Pages\ChargesAssocie;
use App\Filament\Pages\FicheProgramme;
use App\Filament\Pages\ProfilAssocie;
use App\Filament\Resources\ActualiteResource\Pages\ManageActualites;
use App\Filament\Resources\EvenementResource\Pages\ManageEvenements;
use App\Filament\Widgets\EncaissementsProgramme;
use App\Filament\Widgets\GraphiqueCapital;
use App\Filament\Widgets\LotsProgramme;
use App\Filament\Widgets\ResumeCharges;
use App\Filament\Widgets\ResumeStock;
use App\Filament\Widgets\VentesProgramme;
use App\Models\Actualite;
use App\Models\Appartement;
use App\Models\ChargeFixe;
use App\Models\Encaissement;
use App\Models\Evenement;
use App\Models\Programme;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(fn () => Filament::setCurrentPanel(Filament::getPanel('app')));

function apportDe(User $associe, float $montant): void
{
    test()->actingAs(utilisateur(Role::Administrateur));
    Encaissement::create([
        'type' => TypeEncaissement::ApportAssocie, 'associe_id' => $associe->id, 'date_operation' => now(),
        'compte_id' => compte()->id, 'mode' => ModePaiement::Virement, 'montant' => $montant,
    ]);
}

it('montre le stock et la fiche d’un programme sans client', function () {
    $this->actingAs(utilisateur(Role::Administrateur));
    $vente = vente(1200000);
    Encaissement::create([
        'type' => TypeEncaissement::VenteAppartement, 'vente_id' => $vente->id, 'date_operation' => now(),
        'compte_id' => compte()->id, 'mode' => ModePaiement::Virement, 'montant' => 400000,
    ]);
    $libre = appartement(900000);
    $autre = Appartement::create([
        'programme_id' => Programme::create(['nom' => 'Autre programme', 'ville' => 'Rabat'])->id,
        'reference' => 'B1', 'typologie' => 'Studio', 'surface' => 40, 'prix' => 500000,
    ]);
    $programme = $libre->programme;

    $this->actingAs(utilisateur(Role::Associe));

    expect(ResumeStock::chiffres($programme->id))->toBe([
        'lots' => 2, 'disponibles' => 1, 'vendus' => 1, 'valeurStock' => 900000.0, 'prixM2' => 10000.0,
    ])
        ->and(ResumeStock::chiffres()['lots'])->toBe(3)
        ->and(VentesProgramme::chiffres($programme->id))->toBe([
            'vendu' => 1200000.0, 'encaisse' => 400000.0, 'reste' => 800000.0, 'prixM2Vendu' => 13333.33,
        ])
        ->and(end(EncaissementsProgramme::serie($programme->id)['montants']))->toBe(400000.0)
        ->and(EncaissementsProgramme::serie($autre->programme_id)['montants'])->each->toBe(0.0);

    $this->get('/espace-associe/stock')->assertOk()->assertSee('Stock des appartements');
    Livewire::test(LotsProgramme::class)
        ->assertCanSeeTableRecords([$libre, $autre, $vente->appartement])
        ->assertDontSee($vente->client->nom.' '.$vente->client->prenom);
    Livewire::test(LotsProgramme::class, ['filters' => ['programme' => $programme->id]])
        ->assertCanSeeTableRecords([$libre])
        ->assertCanNotSeeTableRecords([$autre]);

    Livewire::test(FicheProgramme::class)->assertOk()->assertSet('filters.programme', $autre->programme_id);
    $this->get(FicheProgramme::urlPour($programme))->assertOk()->assertSee($programme->nom);
    Livewire::test(FicheProgramme::class)->assertSet('filters.programme', $programme->id);
});

it('montre la répartition du capital avec les noms, sans coordonnées', function () {
    $associe = utilisateur(Role::Associe);
    $autre = utilisateur(Role::Associe);
    $autre->forceFill(['rib' => str_repeat('1', 24), 'telephone' => '0600000000'])->save();
    apportDe($associe, 250000);
    apportDe($autre, 750000);

    $this->actingAs($associe);

    expect(GraphiqueCapital::parts())->toBe([$autre->name => 75.0, $associe->name => 25.0]);

    Livewire::test(CapitalAssocie::class)
        ->assertCanSeeTableRecords([$associe, $autre])
        ->assertSee('75,00 %')
        ->assertSee('Vous')
        ->assertDontSee(str_repeat('1', 24))
        ->assertDontSee('0600000000')
        ->assertDontSee($autre->email);
});

it('publie actualités et agenda pour les associés, qui en sont prévenus', function () {
    $associe = utilisateur(Role::Associe);
    $comptable = utilisateur(Role::Comptable);
    $this->actingAs(utilisateur(Role::Administrateur));

    Livewire::test(ManageActualites::class)
        ->callAction('create', ['titre' => 'Lancement du gros œuvre', 'contenu' => "Les travaux démarrent.\nRéunion sur site.", 'epingle' => true])
        ->assertHasNoActionErrors();
    Livewire::test(ManageEvenements::class)
        ->callAction('create', [
            'titre' => 'AG ordinaire', 'type' => TypeEvenement::AssembleeGenerale->value,
            'date_debut' => now()->addDays(10)->format('Y-m-d H:i'), 'lieu' => 'Siège',
        ])
        ->assertHasNoActionErrors();
    $passe = Evenement::create(['titre' => 'Réunion passée', 'type' => TypeEvenement::Reunion, 'date_debut' => now()->subMonth(), 'montant' => 99]);

    expect($associe->notifications()->count())->toBe(3)
        ->and($comptable->notifications()->count())->toBe(0)
        ->and($passe->montant)->toBeNull();

    $this->actingAs($associe);
    $this->get('/espace-associe/actualites')->assertOk()->assertSee('Lancement du gros œuvre')->assertSee('Réunion sur site.');
    Livewire::test(AgendaAssocie::class)
        ->assertCanSeeTableRecords([Evenement::firstWhere('titre', 'AG ordinaire')])
        ->assertCanNotSeeTableRecords([$passe])
        ->filterTable('quand', false)
        ->assertCanSeeTableRecords([$passe]);
    expect(AgendaAssocie::getNavigationBadge())->toBe('1');

    $this->get('/actualites')->assertForbidden();
    $this->get('/agenda')->assertForbidden();
    expect($associe->can('create', Actualite::class))->toBeFalse();
});

it('laisse l’associé renseigner son RIB et prévient l’administrateur', function () {
    $admin = utilisateur(Role::Administrateur);
    $associe = utilisateur(Role::Associe);
    $this->actingAs($associe);

    Livewire::test(ProfilAssocie::class)
        ->fillForm(['banque' => 'Banque test', 'rib' => '1234'])
        ->call('enregistrer')
        ->assertHasFormErrors(['rib']);

    Livewire::test(ProfilAssocie::class)
        ->fillForm(['telephone' => '0611223344', 'banque' => 'Banque test', 'rib' => '0111 2222 3333 4444 5555 6666', 'name' => 'Pirate', 'email' => 'pirate@x.ma'])
        ->call('enregistrer')
        ->assertHasNoFormErrors();

    $associe->refresh();
    expect($associe->rib)->toBe('011122223333444455556666')
        ->and($associe->name)->not->toBe('Pirate')
        ->and($associe->email)->not->toBe('pirate@x.ma')
        ->and($admin->notifications()->get()->pluck('data.title'))->toContain("{$associe->name} a modifié son RIB")
        ->and($admin->notifications()->first()->data['body'])->toContain('6666')->not->toContain('011122223333');
});

it('montre les charges fixes en lecture seule', function () {
    ChargeFixe::create(['libelle' => 'Loyer bureau', 'categorie' => CategorieCharge::Loyer, 'montant' => 6000, 'periodicite' => Periodicite::Mensuelle]);
    ChargeFixe::create(['libelle' => 'Assurance', 'categorie' => CategorieCharge::Assurance, 'montant' => 12000, 'periodicite' => Periodicite::Annuelle]);
    ChargeFixe::create(['libelle' => 'Ancienne', 'categorie' => CategorieCharge::Autre, 'montant' => 999, 'periodicite' => Periodicite::Mensuelle, 'actif' => false]);

    $this->actingAs(utilisateur(Role::Associe));

    expect(ResumeCharges::chiffres())->toBe(['budget' => 7000.0, 'regle' => 0.0, 'retards' => 0]);
    Livewire::test(ChargesAssocie::class)->assertOk()->assertSee('Loyer bureau')->assertDontSee('Ancienne');
    $this->get('/charges-fixes')->assertForbidden();
});

it('range les pages de l’associé en groupes dans la barre latérale', function () {
    $this->actingAs(utilisateur(Role::Associe))->get('/')
        ->assertOk()
        ->assertSeeInOrder(['Mon investissement', 'La société', 'Programmes immobiliers', 'Vie sociale'])
        ->assertSee('/espace-associe/capital', false)
        ->assertSee('/espace-associe/profil', false)
        ->assertSee('/espace-associe/charges', false)
        ->assertSee('/espace-associe/stock', false)
        ->assertSee('/espace-associe/fiche-programme', false)
        ->assertSee('/espace-associe/actualites', false)
        ->assertSee('/espace-associe/agenda', false);
});

it('affiche chaque page de l’espace associé', function (string $url) {
    vente();
    ChargeFixe::create(['libelle' => 'Loyer', 'categorie' => CategorieCharge::Loyer, 'montant' => 5000, 'periodicite' => Periodicite::Mensuelle]);
    apportDe($associe = utilisateur(Role::Associe), 1000);
    Evenement::create(['titre' => 'AG', 'type' => TypeEvenement::AssembleeGenerale, 'date_debut' => now()->addWeek()]);
    Actualite::create(['titre' => 'Bienvenue', 'contenu' => 'Texte']);

    $this->actingAs($associe)->get($url)->assertOk();
})->with(['/mes-investissements', '/espace-associe/capital', '/espace-associe/demandes', '/espace-associe/profil',
    '/espace-associe/resultat', '/espace-associe/tresorerie', '/espace-associe/charges',
    '/espace-associe/programmes', '/espace-associe/fiche-programme', '/espace-associe/stock',
    '/espace-associe/actualites', '/espace-associe/agenda', '/espace-associe/documents']);

it('ferme les nouvelles pages aux autres rôles', function (string $url) {
    $this->actingAs(utilisateur(Role::Comptable))->get($url)->assertForbidden();
    $this->actingAs(utilisateur(Role::Administrateur))->get($url)->assertForbidden();
})->with(['/espace-associe/capital', '/espace-associe/profil', '/espace-associe/charges', '/espace-associe/stock',
    '/espace-associe/fiche-programme', '/espace-associe/actualites', '/espace-associe/agenda']);

it('ouvre à l’administrateur la gestion des actualités et de l’agenda', function () {
    $this->actingAs(utilisateur(Role::Administrateur));
    $this->get('/actualites')->assertOk();
    $this->get('/agenda')->assertOk();
    $this->get('/associes')->assertOk();
});
