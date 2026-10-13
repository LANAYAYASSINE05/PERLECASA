<?php

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Enums\TypeEncaissement;
use App\Enums\TypeEvenement;
use App\Filament\Pages\FicheProgramme;
use App\Filament\Pages\MesInvestissements;
use App\Filament\Pages\VieSocialeAssocie;
use App\Filament\Resources\ActualiteResource\Pages\ManageActualites;
use App\Filament\Resources\EvenementResource\Pages\ManageEvenements;
use App\Filament\Widgets\AgendaAssocies;
use App\Filament\Widgets\DernieresActualites;
use App\Filament\Widgets\EncaissementsProgramme;
use App\Filament\Widgets\GraphiqueCapital;
use App\Filament\Widgets\LotsProgramme;
use App\Filament\Widgets\ResumeStock;
use App\Filament\Widgets\TableauCapital;
use App\Filament\Widgets\VentesProgramme;
use App\Models\Actualite;
use App\Models\Appartement;
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

    Livewire::test(TableauCapital::class)
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
    $this->get('/espace-associe/vie-sociale')->assertOk();
    Livewire::test(DernieresActualites::class)->assertSee('Lancement du gros œuvre')->assertSee('Réunion sur site.');
    Livewire::test(AgendaAssocies::class)
        ->assertCanSeeTableRecords([Evenement::firstWhere('titre', 'AG ordinaire')])
        ->assertCanNotSeeTableRecords([$passe])
        ->filterTable('quand', false)
        ->assertCanSeeTableRecords([$passe]);
    expect(VieSocialeAssocie::getNavigationBadge())->toBe('1');

    $this->get('/actualites')->assertForbidden();
    $this->get('/agenda')->assertForbidden();
    expect($associe->can('create', Actualite::class))->toBeFalse();
});

it('laisse l’associé renseigner son RIB et prévient l’administrateur', function () {
    $admin = utilisateur(Role::Administrateur);
    $associe = utilisateur(Role::Associe);
    $this->actingAs($associe);

    Livewire::test(MesInvestissements::class)
        ->callAction('profil', ['banque' => 'Banque test', 'rib' => '1234'])
        ->assertHasActionErrors(['rib']);

    Livewire::test(MesInvestissements::class)
        ->callAction('profil', ['telephone' => '0611223344', 'banque' => 'Banque test', 'rib' => '0111 2222 3333 4444 5555 6666', 'name' => 'Pirate', 'email' => 'pirate@x.ma'])
        ->assertHasNoActionErrors();

    $associe->refresh();
    expect($associe->rib)->toBe('011122223333444455556666')
        ->and($associe->name)->not->toBe('Pirate')
        ->and($associe->email)->not->toBe('pirate@x.ma')
        ->and($admin->notifications()->get()->pluck('data.title'))->toContain("{$associe->name} a modifié son RIB")
        ->and($admin->notifications()->first()->data['body'])->toContain('6666')->not->toContain('011122223333');
});

it('ne garde que quatre pages dans la barre latérale de l’associé', function () {
    $this->actingAs(utilisateur(Role::Associe))->get('/')
        ->assertOk()
        ->assertSeeInOrder(['Mon investissement', 'La société', 'Documents &amp; agenda', 'Mes demandes'], false)
        ->assertDontSee('/espace-associe/capital', false)
        ->assertDontSee('/espace-associe/charges', false)
        ->assertDontSee('/espace-associe/stock', false)
        ->assertDontSee('/espace-associe/fiche-programme', false);
});

it('affiche chaque page de l’espace associé', function (string $url) {
    vente();
    apportDe($associe = utilisateur(Role::Associe), 1000);
    Evenement::create(['titre' => 'AG', 'type' => TypeEvenement::AssembleeGenerale, 'date_debut' => now()->addWeek()]);
    Actualite::create(['titre' => 'Bienvenue', 'contenu' => 'Texte']);

    $this->actingAs($associe)->get($url)->assertOk();
})->with(['/mes-investissements', '/espace-associe/societe', '/espace-associe/vie-sociale',
    '/espace-associe/demandes', '/espace-associe/fiche-programme']);

it('ferme l’espace associé aux autres rôles', function (string $url) {
    $this->actingAs(utilisateur(Role::Comptable))->get($url)->assertForbidden();
    $this->actingAs(utilisateur(Role::Administrateur))->get($url)->assertForbidden();
})->with(['/mes-investissements', '/espace-associe/societe', '/espace-associe/vie-sociale', '/espace-associe/fiche-programme']);

it('retire les anciennes pages détaillées', function (string $url) {
    $this->actingAs(utilisateur(Role::Associe))->get($url)->assertNotFound();
})->with(['/espace-associe/capital', '/espace-associe/profil', '/espace-associe/charges', '/espace-associe/stock',
    '/espace-associe/resultat', '/espace-associe/tresorerie', '/espace-associe/programmes', '/espace-associe/documents']);

it('ouvre à l’administrateur la gestion des actualités et de l’agenda', function () {
    $this->actingAs(utilisateur(Role::Administrateur));
    $this->get('/actualites')->assertOk();
    $this->get('/agenda')->assertOk();
    $this->get('/associes')->assertOk();
});
