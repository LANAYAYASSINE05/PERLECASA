<?php

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Enums\TypeCompte;
use App\Enums\TypeDecaissement;
use App\Enums\TypeEncaissement;
use App\Filament\Pages\Auth\Connexion;
use App\Filament\Resources\DecaissementResource\Pages\ManageDecaissements;
use App\Filament\Resources\FournisseurResource\Pages\ManageFournisseurs;
use App\Filament\Resources\UserResource\Pages\ManageUsers;
use App\Filament\Resources\VenteResource\Pages\ManageVentes;
use App\Models\Client;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Models\Fournisseur;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Livewire\DatabaseNotifications;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const XSS = '"><img src=x onerror=alert(1)><script>alert("xss")</script>';

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

function operationCaisse(array $attributs = []): Decaissement
{
    return Decaissement::create($attributs + [
        'type' => TypeDecaissement::OperationCaisse, 'operateur' => 'Karim', 'motif' => 'Taxi', 'date_operation' => now(),
        'compte_id' => compte(TypeCompte::Caisse, 10000)->id, 'mode' => ModePaiement::Especes, 'montant' => 200,
    ]);
}

function decaissementFournisseur(): Decaissement
{
    return Decaissement::create([
        'type' => TypeDecaissement::Fournisseur, 'fournisseur_id' => Fournisseur::create(['raison_sociale' => 'Sotravaux'])->id,
        'date_operation' => now(), 'compte_id' => compte()->id, 'mode' => ModePaiement::Virement, 'montant' => 5000,
    ]);
}

/** Une fois téléversé, le type MIME est détecté à partir du contenu stocké, pas du nom. */
function fichier(string $nom, string $contenu): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nom, $contenu);
}

function televerser(UploadedFile $fichier)
{
    $operation = operationCaisse();

    return [$operation, Livewire::test(ManageDecaissements::class)
        ->mountTableAction('justifier', $operation)
        ->setTableActionData(['piece_justificative' => [$fichier]])
        ->callMountedTableAction()];
}

const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

/* ---------- XSS ---------- */

it('échappe les données saisies dans les tableaux', function () {
    $this->actingAs(utilisateur(Role::Administrateur));

    $vente = vente();
    Client::whereKey($vente->client_id)->update(['nom' => XSS]);
    Fournisseur::create(['raison_sociale' => XSS]);
    utilisateur(Role::Comptable)->update(['name' => XSS]);

    foreach ([ManageVentes::class, ManageFournisseurs::class, ManageUsers::class] as $page) {
        Livewire::test($page)
            ->assertSee(XSS)
            ->assertDontSeeHtml('<img src=x onerror')
            ->assertDontSeeHtml('<script>alert("xss")');
    }
});

it('échappe les données saisies dans les notifications', function () {
    $comptable = utilisateur(Role::Comptable);
    $caissier = utilisateur(Role::Caissier);
    $caissier->update(['name' => XSS]);

    $this->actingAs($caissier);
    operationCaisse(['motif' => XSS]);

    $this->actingAs($comptable);
    expect($comptable->notifications()->count())->toBe(1);

    Livewire::withoutLazyLoading()->test(DatabaseNotifications::class)
        ->assertSee('alert(1)')
        ->assertDontSeeHtml('<img src=x onerror')
        ->assertDontSeeHtml('<script>alert("xss")');
});

/* ---------- Fichiers téléversés ---------- */

it('accepte un vrai PDF et le range sous un nom aléatoire', function () {
    Storage::fake(Decaissement::DISQUE);
    $this->actingAs(utilisateur(Role::Caissier));

    [$operation, $page] = televerser(fichier('../../.env.pdf', PDF));

    $page->assertHasNoTableActionErrors();
    $chemin = $operation->refresh()->piece_justificative;

    expect($operation->justifie)->toBeTrue()
        ->and($chemin)->toMatch('#^justificatifs/[0-9A-Z]{26}\.pdf$#');
    Storage::disk(Decaissement::DISQUE)->assertExists($chemin);
});

it('refuse les fichiers dangereux', function (string $nom, string $contenu) {
    Storage::fake(Decaissement::DISQUE);
    $this->actingAs(utilisateur(Role::Caissier));

    [$operation, $page] = televerser(fichier($nom, $contenu));

    $page->assertHasTableActionErrors(['piece_justificative']);
    expect($operation->refresh()->piece_justificative)->toBeNull();
})->with([
    'script PHP' => ['shell.php', '<?php system($_GET["c"]); ?>'],
    'PHP déguisé en PDF' => ['facture.pdf', '<?php system($_GET["c"]); ?>'],
    'double extension' => ['shell.php.pdf', '<?php echo 1; ?>'],
    'PDF renommé en .php' => ['shell.php', PDF],
    'SVG avec script' => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'page HTML' => ['page.html', '<html><body><script>alert(1)</script></body></html>'],
    'PDF de plus de 5 Mo' => ['gros.pdf', PDF.str_repeat('0', 6 * 1024 * 1024)],
]);

/* ---------- Connexion ---------- */

it('bloque la connexion après 5 échecs, même avec le bon mot de passe ensuite', function () {
    $utilisateur = utilisateur();

    $page = Livewire::test(Connexion::class);
    foreach (range(1, 5) as $_) {
        $page->fillForm(['email' => $utilisateur->email, 'password' => 'mauvais'])->call('authenticate')
            ->assertHasFormErrors(['email']);
    }

    $page->fillForm(['email' => $utilisateur->email, 'password' => 'password'])->call('authenticate')
        ->assertNotified();

    $this->assertGuest();
});

it('refuse la connexion d’un compte désactivé et d’une injection SQL', function () {
    $inactif = utilisateur();
    $inactif->update(['actif' => false]);

    Livewire::test(Connexion::class)
        ->fillForm(['email' => $inactif->email, 'password' => 'password'])->call('authenticate')
        ->assertHasFormErrors(['email']);

    Livewire::test(Connexion::class)
        ->fillForm(['email' => "' OR 1=1 --", 'password' => 'x'])->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('coupe l’accès d’un compte désactivé pendant sa session', function () {
    $utilisateur = utilisateur();
    $this->actingAs($utilisateur)->get('/')->assertOk();

    $utilisateur->update(['actif' => false]);

    $this->get('/')->assertForbidden();
    $this->get('/ventes')->assertForbidden();
});

it('impose une politique de mot de passe à la création des comptes', function () {
    $this->actingAs(utilisateur(Role::Administrateur));
    $creer = fn (string $motDePasse, string $email) => Livewire::test(ManageUsers::class)
        ->mountAction('create')
        ->setActionData(['name' => 'Nadia', 'email' => $email, 'role' => Role::Comptable->value, 'password' => $motDePasse])
        ->callMountedAction();

    foreach (['abc', 'motdepasse', '12345678', 'motdepasse12', 'MOTDEPASSE12'] as $faible) {
        $creer($faible, "faible-{$faible}@perlacasa.ma")->assertHasActionErrors(['password']);
    }

    $creer('Perle2026Casa', 'nadia@perlacasa.ma')->assertHasNoActionErrors();
    $nadia = User::firstWhere('email', 'nadia@perlacasa.ma');

    expect($nadia->password)->not->toBe('Perle2026Casa')
        ->and(Hash::check('Perle2026Casa', $nadia->password))->toBeTrue();
});

/* ---------- CSRF ---------- */

it('rejette une requête sans jeton CSRF (419)', function () {
    $actif = fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    };
    $this->app->bind(ValidateCsrfToken::class, $actif);
    $this->app->bind(VerifyCsrfToken::class, $actif);

    $this->actingAs(utilisateur());

    $this->post('/logout')->assertStatus(419);
    $this->assertAuthenticated();

    $this->withSession(['_token' => 'jeton-valide'])->post('/logout', ['_token' => 'jeton-valide'])->assertRedirect();
    $this->assertGuest();
});

/* ---------- Pages réservées et IDOR ---------- */

it('interdit au caissier les pièces hors de son périmètre', function () {
    $this->actingAs(utilisateur(Role::Administrateur));
    $vente = vente();
    $encaissement = Encaissement::create([
        'type' => TypeEncaissement::VenteAppartement, 'vente_id' => $vente->id, 'date_operation' => now(),
        'compte_id' => compte()->id, 'mode' => ModePaiement::Virement, 'montant' => 1000,
    ]);
    $fournisseur = decaissementFournisseur();

    $this->actingAs(utilisateur(Role::Caissier));

    $this->get("/ventes/{$vente->id}/facture")->assertForbidden();
    $this->get("/encaissements/{$encaissement->id}/recu")->assertForbidden();
    $this->get("/decaissements/{$fournisseur->id}/piece")->assertForbidden();
    $this->get('/encaissements')->assertForbidden();
    $this->get('/utilisateurs')->assertForbidden();
});

it('interdit au caissier de modifier ou supprimer un décaissement fournisseur, même par appel Livewire direct', function () {
    $this->actingAs(utilisateur(Role::Administrateur));
    $fournisseur = decaissementFournisseur();

    $caissier = utilisateur(Role::Caissier);
    $this->actingAs($caissier);
    $caisse = operationCaisse();

    expect($caissier->can('view', $fournisseur))->toBeFalse()
        ->and($caissier->can('update', $fournisseur))->toBeFalse()
        ->and($caissier->can('delete', $fournisseur))->toBeFalse()
        ->and($caissier->can('update', $caisse))->toBeFalse()
        ->and($caissier->can('view', $caisse))->toBeTrue();

    Livewire::test(ManageDecaissements::class)
        ->assertCanNotSeeTableRecords([$fournisseur])
        ->call('mountTableAction', 'delete', (string) $fournisseur->id)
        ->call('callMountedTableAction')
        ->call('mountTableAction', 'edit', (string) $fournisseur->id)
        ->set('mountedTableActionsData.0.montant', 1)
        ->call('callMountedTableAction')
        ->call('mountTableAction', 'edit', (string) $caisse->id)
        ->set('mountedTableActionsData.0.montant', 1)
        ->call('callMountedTableAction')
        ->call('mountTableAction', 'delete', (string) $caisse->id)
        ->call('callMountedTableAction');

    expect($fournisseur->fresh())->not->toBeNull()
        ->and((float) $fournisseur->fresh()->montant)->toBe(5000.0)
        ->and($caisse->fresh())->not->toBeNull()
        ->and((float) $caisse->fresh()->montant)->toBe(200.0);
});

it('interdit au comptable l’écran Utilisateurs, même par Livewire', function () {
    $this->actingAs(utilisateur(Role::Comptable));

    Livewire::test(ManageUsers::class)->assertForbidden();
});

it('empêche l’administrateur de se supprimer ou de se désactiver', function () {
    $admin = utilisateur(Role::Administrateur);
    $this->actingAs($admin);

    expect($admin->can('delete', $admin))->toBeFalse();

    Livewire::test(ManageUsers::class)
        ->call('mountTableAction', 'delete', (string) $admin->id)
        ->call('callMountedTableAction')
        ->call('mountTableAction', 'edit', (string) $admin->id)
        ->set('mountedTableActionsData.0.actif', false)
        ->call('callMountedTableAction');

    expect($admin->fresh())->not->toBeNull()
        ->and($admin->fresh()->actif)->toBeTrue();
});

it('répond 404 sans trace pour un identifiant inexistant', function () {
    config(['app.debug' => false]);
    $this->actingAs(utilisateur(Role::Administrateur));

    $this->get('/ventes/999999/facture')->assertNotFound()->assertDontSee('ModelNotFoundException');
});

/* ---------- Erreurs et en-têtes ---------- */

it('n’affiche aucune trace sur une erreur 500 quand le debug est désactivé', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/_erreur-test', fn () => throw new RuntimeException('SQLSTATE secret-interne'));

    $this->get('/_erreur-test')
        ->assertStatus(500)
        ->assertDontSee('secret-interne')
        ->assertDontSee('RuntimeException')
        ->assertDontSee('SQLSTATE')
        ->assertDontSee(str_replace('\\', '/', base_path()))
        ->assertDontSee(base_path());
});

it('envoie les en-têtes de sécurité HTTP', function () {
    $reponse = $this->get('/login')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security');

    $csp = $reponse->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->and($reponse->headers->get('Permissions-Policy'))->toContain('camera=()');
});

it('ajoute HSTS en production', function () {
    $this->app['env'] = 'production';

    $this->get('/login')->assertHeader('Strict-Transport-Security');
});
