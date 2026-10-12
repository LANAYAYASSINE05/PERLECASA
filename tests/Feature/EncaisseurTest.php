<?php

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Enums\TypeEncaissement;
use App\Filament\Resources\CompteResource;
use App\Filament\Resources\EncaissementResource\Pages\ManageEncaissements;
use App\Filament\Widgets\BandeauAccueil;
use App\Filament\Widgets\ChiffresFinance;
use App\Filament\Widgets\FluxMensuels;
use App\Models\Encaissement;
use App\Models\User;
use Livewire\Livewire;

function encaissementDe(User $auteur, float $montant = 1000): Encaissement
{
    test()->actingAs($auteur);

    return Encaissement::create([
        'type' => TypeEncaissement::VirementRecu, 'emetteur' => 'Client', 'date_operation' => now(),
        'compte_id' => compte()->id, 'mode' => ModePaiement::Virement, 'montant' => $montant,
    ]);
}

it('laisse l’agent d’encaissement saisir un encaissement sur une vente', function () {
    $agent = utilisateur(Role::Encaisseur);
    $vente = vente(500000);
    $this->actingAs($agent);

    Livewire::test(ManageEncaissements::class)
        ->callAction('create', [
            'type' => TypeEncaissement::VenteAppartement->value,
            'vente_id' => $vente->id,
            'date_operation' => now()->toDateString(),
            'compte_id' => compte()->id,
            'mode' => ModePaiement::Cheque->value,
            'montant' => 100000,
        ])
        ->assertHasNoActionErrors();

    expect(Encaissement::sole())
        ->cree_par->toBe($agent->id)
        ->and((float) Encaissement::sole()->montant)->toBe(100000.0);
});

it('ne montre à l’agent d’encaissement que ses propres encaissements et reçus', function () {
    $autre = encaissementDe(utilisateur(Role::Comptable));
    $agent = utilisateur(Role::Encaisseur);
    $sien = encaissementDe($agent);

    expect($agent->can('view', $sien))->toBeTrue()
        ->and($agent->can('view', $autre))->toBeFalse();

    Livewire::test(ManageEncaissements::class)
        ->assertCanSeeTableRecords([$sien])
        ->assertCanNotSeeTableRecords([$autre]);

    $this->get("/encaissements/{$sien->id}/recu")->assertOk();
    $this->get("/encaissements/{$autre->id}/recu")->assertForbidden();
});

it('interdit à l’agent d’encaissement de modifier ou supprimer, même par appel Livewire direct', function () {
    $agent = utilisateur(Role::Encaisseur);
    $sien = encaissementDe($agent, 1000);

    expect($agent->can('update', $sien))->toBeFalse()
        ->and($agent->can('delete', $sien))->toBeFalse()
        ->and($agent->can('deleteAny', Encaissement::class))->toBeFalse();

    Livewire::test(ManageEncaissements::class)
        ->assertTableActionHidden('edit', $sien)
        ->assertTableActionHidden('delete', $sien)
        ->call('mountTableAction', 'edit', (string) $sien->id)
        ->set('mountedTableActionsData.0.montant', 1)
        ->call('callMountedTableAction')
        ->call('mountTableAction', 'delete', (string) $sien->id)
        ->call('callMountedTableAction');

    expect($sien->fresh())->not->toBeNull()
        ->and((float) $sien->fresh()->montant)->toBe(1000.0);
});

it('cache à l’agent d’encaissement la trésorerie, les soldes et la caisse', function () {
    $compte = compte(soldeInitial: 123456);
    $this->actingAs(utilisateur(Role::Encaisseur));

    expect(ChiffresFinance::canView())->toBeFalse()
        ->and(FluxMensuels::canView())->toBeFalse()
        ->and(CompteResource::options()[$compte->id])->toBe($compte->nom);

    Livewire::test(BandeauAccueil::class)
        ->assertSee('Nouvel encaissement')
        ->assertDontSee('Caisse à justifier')
        ->assertDontSee('Nouveau décaissement');
});

it('n’envoie pas les alertes des autres encaissements à l’agent d’encaissement', function () {
    $agent = utilisateur(Role::Encaisseur);
    $comptable = utilisateur(Role::Comptable);

    encaissementDe(utilisateur(Role::Administrateur));

    expect($agent->notifications()->count())->toBe(0)
        ->and($comptable->notifications()->count())->toBe(1);
});
