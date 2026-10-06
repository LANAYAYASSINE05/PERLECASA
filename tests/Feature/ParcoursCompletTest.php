<?php

use App\Enums\Role;
use App\Filament\Resources;
use App\Filament\Widgets;
use App\Models\BonCommande;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Models\Vente;
use Database\Seeders\DemoSeeder;
use Filament\Livewire\DatabaseNotifications;
use Filament\Tables\Columns\Column;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Broadcast;
use Livewire\Livewire;

/** Écran => [URL, page Livewire, code d'accès de User::accede()]. */
const PARCOURS = [
    'ventes' => ['/ventes', Resources\VenteResource\Pages\ManageVentes::class, 'ventes'],
    'encaissements' => ['/encaissements', Resources\EncaissementResource\Pages\ManageEncaissements::class, 'encaissements'],
    'décaissements' => ['/decaissements', Resources\DecaissementResource\Pages\ManageDecaissements::class, 'decaissements'],
    'charges fixes' => ['/charges-fixes', Resources\ChargeFixeResource\Pages\ManageChargeFixes::class, 'charges'],
    'fournisseurs' => ['/fournisseurs', Resources\FournisseurResource\Pages\ManageFournisseurs::class, 'fournisseurs'],
    'bons de commande' => ['/bons-commande', Resources\BonCommandeResource\Pages\ManageBonsCommande::class, 'fournisseurs'],
    'comptes' => ['/comptes', Resources\CompteResource\Pages\ManageComptes::class, 'comptes'],
    'programmes' => ['/programmes', Resources\ProgrammeResource\Pages\ManageProgrammes::class, 'referentiel'],
    'appartements' => ['/appartements', Resources\AppartementResource\Pages\ManageAppartements::class, 'referentiel'],
    'clients' => ['/clients', Resources\ClientResource\Pages\ManageClients::class, 'referentiel'],
    'utilisateurs' => ['/utilisateurs', Resources\UserResource\Pages\ManageUsers::class, 'administration'],
];

beforeEach(function () {
    $this->seed(DemoSeeder::class);
});

it('n’envoie aucune notification pendant le chargement des données de démonstration', function () {
    expect(DatabaseNotification::count())->toBe(0);
});

it('ouvre chaque écran selon le rôle', function (Role $role) {
    $utilisateur = utilisateur($role);
    $this->actingAs($utilisateur);

    $this->get('/')->assertOk();
    $this->get('/profile')->assertOk();

    foreach (PARCOURS as $nom => [$url, , $ecran]) {
        $attendu = $ecran === 'administration' ? $utilisateur->estAdministrateur() : $utilisateur->accede($ecran);

        expect($this->get($url)->status())->toBe($attendu ? 200 : 403, "{$role->value} → {$nom}");
    }
})->with([Role::Administrateur, Role::Comptable, Role::Caissier]);

it('manipule chaque tableau : recherche, tri, filtres, pagination, formulaires', function (string $url, string $page) {
    $this->actingAs(utilisateur(Role::Administrateur));

    $composant = Livewire::test($page)->assertSuccessful();
    $table = $composant->instance()->getTable();

    $composant->searchTable('a')->assertSuccessful()->searchTable(null);

    collect($table->getColumns())
        ->filter(fn (Column $colonne) => $colonne->isSortable())
        ->each(fn (Column $colonne) => $composant->sortTable($colonne->getName())->assertSuccessful()
            ->sortTable($colonne->getName(), 'desc')->assertSuccessful());

    $composant->resetTableFilters()->assertSuccessful()
        ->set('tableRecordsPerPage', 5)->assertSuccessful()
        ->call('gotoPage', 2)->assertSuccessful()
        ->set('tableRecordsPerPage', 'all')->assertSuccessful();

    if ($composant->instance()->getAction('create')?->isVisible()) {
        $composant->mountAction('create')->assertSuccessful()->assertHasNoActionErrors();
    }

    $premier = $table->getQuery()->first();
    if ($premier && $table->getAction('edit')) {
        $composant->mountTableAction('edit', $premier)->assertSuccessful();
    }
})->with(collect(PARCOURS)->map(fn ($p) => [$p[0], $p[1]])->all());

it('affiche chaque widget du tableau de bord', function (string $widget) {
    $this->actingAs(utilisateur(Role::Administrateur));

    Livewire::test($widget)->assertSuccessful();
})->with([
    Widgets\BandeauAccueil::class,
    Widgets\ChiffresFinance::class,
    Widgets\FluxMensuels::class,
    Widgets\CaisseNonJustifiee::class,
    Widgets\ChargesEnRetard::class,
]);

it('télécharge chaque pièce Excel et la pièce justificative', function () {
    $this->actingAs(utilisateur(Role::Administrateur));

    $this->get('/ventes/'.Vente::first()->id.'/facture')->assertOk()->assertDownload();
    $this->get('/encaissements/'.Encaissement::first()->id.'/recu')->assertOk()->assertDownload();
    $this->get('/bons-commande/'.BonCommande::first()->id.'/imprimer')->assertOk()->assertDownload();

    $justifie = Decaissement::where('justifie', true)->whereNotNull('piece_justificative')->first();
    if ($justifie) {
        expect($this->get('/decaissements/'.$justifie->id.'/piece')->getStatusCode())->toBeIn([200, 404]);
    }
});

it('ouvre la cloche et autorise le canal privé de l’utilisateur seulement', function () {
    $utilisateur = utilisateur(Role::Comptable);
    $autre = utilisateur(Role::Caissier);
    $this->actingAs($utilisateur);

    Livewire::test(DatabaseNotifications::class)->assertSuccessful();

    config(['broadcasting.default' => 'reverb']);
    Broadcast::purge('reverb');
    Broadcast::setDefaultDriver('reverb');
    require base_path('routes/channels.php');
    $this->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-App.Models.User.{$utilisateur->id}"])->assertOk();
    $this->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-App.Models.User.{$autre->id}"])->assertForbidden();
});

it('redirige vers la connexion sans session', function () {
    foreach (PARCOURS as [$url]) {
        $this->get($url)->assertRedirect('/login');
    }

    $this->get('/login')->assertOk();
});
