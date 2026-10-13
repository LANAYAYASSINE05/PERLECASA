<?php

use App\Enums\CategorieDocument;
use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Enums\StatutDemande;
use App\Enums\TypeDecaissement;
use App\Enums\TypeDemande;
use App\Enums\TypeEncaissement;
use App\Filament\Pages\DemandesAssocie;
use App\Filament\Pages\SocieteAssocie;
use App\Filament\Resources\DemandeAssocieResource\Pages\ManageDemandesAssocies;
use App\Filament\Resources\DocumentAssocieResource\Pages\ManageDocumentsAssocies;
use App\Filament\Widgets\DocumentsPartages;
use App\Filament\Widgets\EvolutionResultat;
use App\Filament\Widgets\RepartitionDepenses;
use App\Filament\Widgets\ResteAEncaisser;
use App\Filament\Widgets\ResultatSimplifie;
use App\Filament\Widgets\SyntheseTresorerie;
use App\Models\Decaissement;
use App\Models\DemandeAssocie;
use App\Models\DocumentAssocie;
use App\Models\Encaissement;
use App\Models\Fournisseur;
use App\Support\Periode;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Filament::setCurrentPanel(Filament::getPanel('app')));

function depenseFournisseur(float $montant, $date, $compte): Decaissement
{
    return Decaissement::create([
        'type' => TypeDecaissement::Fournisseur, 'fournisseur_id' => Fournisseur::create(['raison_sociale' => 'F '.uniqid()])->id,
        'date_operation' => $date, 'compte_id' => $compte->id, 'mode' => ModePaiement::Virement, 'montant' => $montant,
    ]);
}

it('filtre le résultat et la trésorerie par période', function () {
    $banque = compte(soldeInitial: 0);
    $this->actingAs(utilisateur(Role::Administrateur));

    $vente = vente(1000000);
    Encaissement::create([
        'type' => TypeEncaissement::VenteAppartement, 'vente_id' => $vente->id, 'date_operation' => now(),
        'compte_id' => $banque->id, 'mode' => ModePaiement::Virement, 'montant' => 300000,
    ]);
    depenseFournisseur(100000, now(), $banque);
    depenseFournisseur(40000, now()->subYears(2), $banque);

    $this->actingAs(utilisateur(Role::Associe));

    expect(ResultatSimplifie::chiffres(Periode::depuisFiltres(['periode' => 'mois']))['depenses'])->toBe(100000.0)
        ->and(ResultatSimplifie::chiffres()['depenses'])->toBe(140000.0)
        ->and(RepartitionDepenses::repartition(Periode::depuisFiltres(['periode' => 'annee'])))->toBe(['Fournisseur' => 100000.0]);

    $series = EvolutionResultat::series();
    expect($series['labels'])->toHaveCount(12)
        ->and(end($series['ventes']))->toBe(300000.0)
        ->and(end($series['resultat']))->toBe(200000.0);

    Livewire::test(SocieteAssocie::class)->assertOk()->assertSet('filters.periode', 'annee')->assertSee('Depuis le début');
    Livewire::test(SyntheseTresorerie::class, ['filters' => ['periode' => 'tout']])->assertOk();
});

it('liste le reste à encaisser par vente sans nommer le client', function () {
    $this->actingAs(utilisateur(Role::Administrateur));
    $partielle = vente(1000000);
    $soldee = vente(500000);
    foreach ([[$partielle, 250000], [$soldee, 500000]] as [$v, $montant]) {
        Encaissement::create([
            'type' => TypeEncaissement::VenteAppartement, 'vente_id' => $v->id, 'date_operation' => now(),
            'compte_id' => compte()->id, 'mode' => ModePaiement::Virement, 'montant' => $montant,
        ]);
    }

    $this->actingAs(utilisateur(Role::Associe));

    Livewire::test(ResteAEncaisser::class)
        ->assertCanSeeTableRecords([$partielle])
        ->assertCanNotSeeTableRecords([$soldee])
        ->assertSee('750 000,00')
        ->assertDontSee($partielle->client->nom.' '.$partielle->client->prenom);
});

it('partage les documents de l’administrateur avec les associés seulement', function () {
    Storage::fake(DocumentAssocie::DISQUE);
    $associe = utilisateur(Role::Associe);
    $this->actingAs(utilisateur(Role::Administrateur));

    Livewire::test(ManageDocumentsAssocies::class)
        ->callAction('create', [
            'titre' => 'PV AG 2026',
            'categorie' => CategorieDocument::ProcesVerbal->value,
            'date_document' => now()->toDateString(),
            'fichier' => UploadedFile::fake()->createWithContent('pv.pdf', "%PDF-1.4\n%%EOF\n"),
        ])
        ->assertHasNoActionErrors();

    Livewire::test(ManageDocumentsAssocies::class)
        ->callAction('create', [
            'titre' => 'Script déguisé',
            'categorie' => CategorieDocument::Autre->value,
            'date_document' => now()->toDateString(),
            'fichier' => UploadedFile::fake()->createWithContent('faux.pdf', '<?php echo 1;'),
        ])
        ->assertHasActionErrors(['fichier']);

    $document = DocumentAssocie::sole();
    Storage::disk(DocumentAssocie::DISQUE)->assertExists($document->fichier);
    expect($associe->notifications()->get()->pluck('data.title')->implode(' '))->toContain('PV AG 2026');

    $this->actingAs($associe);
    Livewire::test(DocumentsPartages::class)->assertCanSeeTableRecords([$document]);
    $this->get('/espace-associe/vie-sociale')->assertOk();
    $this->get("/documents-associes/{$document->id}/telecharger")->assertOk();
    $this->get('/documents-associes')->assertForbidden();

    $this->actingAs(utilisateur(Role::Comptable));
    $this->get("/documents-associes/{$document->id}/telecharger")->assertForbidden();
    $this->get('/espace-associe/vie-sociale')->assertForbidden();
});

it('fait circuler une demande de l’associé vers l’administrateur et sa réponse en retour', function () {
    $admin = utilisateur(Role::Administrateur);
    $associe = utilisateur(Role::Associe);
    $autre = utilisateur(Role::Associe);

    $this->actingAs($associe);
    Livewire::test(DemandesAssocie::class)
        ->callAction('create', [
            'type' => TypeDemande::Remboursement->value,
            'objet' => 'Retrait partiel',
            'montant' => 50000,
            'message' => 'Je souhaite retirer une partie de mon apport.',
        ])
        ->assertHasNoActionErrors();

    $demande = DemandeAssocie::sole();
    expect($demande->associe_id)->toBe($associe->id)
        ->and($demande->statut)->toBe(StatutDemande::EnAttente)
        ->and($admin->notifications()->count())->toBe(1)
        ->and($associe->notifications()->count())->toBe(0);

    $this->actingAs($autre);
    Livewire::test(DemandesAssocie::class)->assertCanNotSeeTableRecords([$demande]);
    expect($autre->can('view', $demande))->toBeFalse()
        ->and($autre->can('update', $demande))->toBeFalse();
    $this->get('/demandes-associes')->assertForbidden();

    $this->actingAs($admin);
    Livewire::test(ManageDemandesAssocies::class)
        ->assertCanSeeTableRecords([$demande])
        ->callTableAction('traiter', $demande, ['statut' => StatutDemande::Refusee->value, 'reponse' => 'Pas avant la clôture.'])
        ->assertHasNoTableActionErrors();

    $demande->refresh();
    expect($demande->statut)->toBe(StatutDemande::Refusee)
        ->and($demande->traite_par)->toBe($admin->id)
        ->and($associe->notifications()->count())->toBe(1)
        ->and($autre->notifications()->count())->toBe(0);

    $this->actingAs($associe);
    Livewire::test(DemandesAssocie::class)->assertCanSeeTableRecords([$demande])->assertSee('Pas avant la clôture.');
});

it('ferme au comptable la gestion des documents et des demandes', function (string $url) {
    $this->actingAs(utilisateur(Role::Comptable))->get($url)->assertForbidden();
})->with(['/documents-associes', '/demandes-associes', '/espace-associe/demandes']);
