<?php

use App\Enums\CategorieCharge;
use App\Enums\ModePaiement;
use App\Enums\Periodicite;
use App\Enums\Role;
use App\Enums\TypeCompte;
use App\Enums\TypeDecaissement;
use App\Enums\TypeEncaissement;
use App\Models\ChargeFixe;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Services\Alertes;
use Filament\Livewire\DatabaseNotifications;

it('notifie un encaissement aux autres utilisateurs qui ont l’écran, pas à son auteur', function () {
    $auteur = utilisateur(Role::Comptable);
    $admin = utilisateur(Role::Administrateur);
    $caissier = utilisateur(Role::Caissier);
    $vente = vente(500000);

    $this->actingAs($auteur);
    Encaissement::create([
        'type' => TypeEncaissement::VenteAppartement, 'vente_id' => $vente->id, 'date_operation' => now(),
        'compte_id' => compte()->id, 'mode' => ModePaiement::Virement, 'montant' => 100000,
    ]);

    $titres = $admin->notifications()->pluck('data')->pluck('title');

    expect($titres->all())->toHaveCount(1)
        ->and($titres->first())->toStartWith('Encaissement ENC-')
        ->and($auteur->notifications()->count())->toBe(0)
        ->and($caissier->notifications()->count())->toBe(0);
});

it('alerte une opération de caisse non justifiée, puis sa justification', function () {
    $caissier = utilisateur(Role::Caissier);
    $comptable = utilisateur(Role::Comptable);

    $this->actingAs($caissier);
    $operation = Decaissement::create([
        'type' => TypeDecaissement::OperationCaisse, 'operateur' => 'Youssef', 'motif' => 'Taxi', 'date_operation' => now(),
        'compte_id' => compte(TypeCompte::Caisse, 1000)->id, 'mode' => ModePaiement::Especes, 'montant' => 120,
    ]);

    $this->actingAs(utilisateur(Role::Administrateur));
    $operation->update(['piece_justificative' => 'pieces/taxi.pdf']);

    expect($comptable->notifications()->pluck('data')->pluck('title')->all())->toEqualCanonicalizing([
        "Opération de caisse {$operation->numero} non justifiée",
        "Opération de caisse {$operation->numero} justifiée",
    ]);
});

it('ne notifie rien hors d’une action utilisateur (seeders, tinker)', function () {
    $admin = utilisateur(Role::Administrateur);

    vente();

    expect($admin->notifications()->count())->toBe(0);
});

it('rappelle les charges fixes en retard par la commande planifiée', function () {
    $admin = utilisateur(Role::Administrateur);
    $caissier = utilisateur(Role::Caissier);
    $charge = ChargeFixe::create([
        'libelle' => 'Loyer bureau', 'categorie' => CategorieCharge::cases()[0], 'montant' => 8000, 'periodicite' => Periodicite::Mensuelle,
    ]);
    Decaissement::create([
        'type' => TypeDecaissement::ChargeFixe, 'charge_fixe_id' => $charge->id, 'periode' => now()->subMonths(3)->format('Y-m'),
        'date_operation' => now()->subMonths(3), 'compte_id' => compte()->id, 'mode' => ModePaiement::Virement, 'montant' => 8000,
    ]);

    $this->artisan('finance:alertes-charges')->assertSuccessful();

    expect($admin->notifications()->first()?->data['title'])->toBe('1 charge fixe en retard')
        ->and($caissier->notifications()->count())->toBe(0);
});

it('affiche la cloche des notifications', function () {
    $this->actingAs(utilisateur(Role::Administrateur))->get('/ventes')
        ->assertSeeLivewire(DatabaseNotifications::class)
        ->assertSee('window.Echo = new window.EchoFactory', false);
});

it('ne bloque pas l’enregistrement si le serveur Reverb est injoignable', function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.options.port' => 1]);
    $admin = utilisateur(Role::Administrateur);

    $this->actingAs(utilisateur(Role::Comptable));
    $vente = vente();

    expect($vente->exists)->toBeTrue()
        ->and($admin->notifications()->count())->toBe(1);
});

it('expose le rappel des charges comme service', function () {
    expect(Alertes::charges())->toBe(0);
});
