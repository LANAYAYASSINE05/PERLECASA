<?php

use App\Enums\CategorieCharge;
use App\Enums\ModePaiement;
use App\Enums\Periodicite;
use App\Enums\StatutAppartement;
use App\Enums\TypeCompte;
use App\Enums\TypeDecaissement;
use App\Enums\TypeEncaissement;
use App\Models\ChargeFixe;
use App\Models\Client;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Models\Fournisseur;
use App\Models\Vente;
use Illuminate\Validation\ValidationException;

function encaisser(Vente $vente, float $montant, $compte): Encaissement
{
    return Encaissement::create([
        'type' => TypeEncaissement::VenteAppartement, 'vente_id' => $vente->id, 'date_operation' => now(),
        'compte_id' => $compte->id, 'mode' => ModePaiement::Virement, 'montant' => $montant,
    ]);
}

it('passe l’appartement en vendu et numérote la vente', function () {
    $vente = vente();

    expect($vente->numero)->toMatch('/^VTE-\d{4}-0001$/')
        ->and($vente->appartement->fresh()->statut)->toBe(StatutAppartement::Vendu);
});

it('refuse de vendre deux fois le même appartement', function () {
    $vente = vente();

    Vente::create([
        'appartement_id' => $vente->appartement_id,
        'client_id' => Client::create(['nom' => 'Autre'])->id,
        'date_vente' => now(), 'prix_vente' => 1,
    ]);
})->throws(ValidationException::class);

it('rend l’appartement disponible quand la vente est annulée', function () {
    $vente = vente();
    $vente->delete();

    expect($vente->appartement->fresh()->statut)->toBe(StatutAppartement::Disponible);
});

it('suit le reste dû et refuse d’encaisser plus que le prix', function () {
    $vente = vente(500000);
    $banque = compte();

    encaisser($vente, 150000, $banque);
    expect($vente->resteDu())->toBe(350000.0);

    expect(fn () => encaisser($vente, 350000.01, $banque))
        ->toThrow(ValidationException::class, 'reste dû');
});

it('bloque l’annulation d’une vente déjà encaissée', function () {
    $vente = vente();
    encaisser($vente, 1000, compte());

    $vente->delete();
})->throws(ValidationException::class);

it('exige l’émetteur d’un virement reçu', function () {
    Encaissement::create([
        'type' => TypeEncaissement::VirementRecu, 'date_operation' => now(),
        'compte_id' => compte()->id, 'mode' => ModePaiement::Virement, 'montant' => 1000,
    ]);
})->throws(ValidationException::class, 'qui a fait le virement');

it('refuse un décaissement supérieur au solde du compte', function () {
    $banque = compte(TypeCompte::Banque, 1000);
    $fournisseur = Fournisseur::create(['raison_sociale' => 'Fournisseur']);

    Decaissement::create([
        'type' => TypeDecaissement::Fournisseur, 'fournisseur_id' => $fournisseur->id, 'date_operation' => now(),
        'compte_id' => $banque->id, 'mode' => ModePaiement::Virement, 'montant' => 1000.01,
    ]);
})->throws(ValidationException::class, 'Solde insuffisant');

it('fait sortir une opération de caisse uniquement d’un compte de caisse', function () {
    Decaissement::create([
        'type' => TypeDecaissement::OperationCaisse, 'operateur' => 'Karim', 'date_operation' => now(),
        'compte_id' => compte(TypeCompte::Banque)->id, 'mode' => ModePaiement::Especes, 'montant' => 100,
    ]);
})->throws(ValidationException::class, 'compte de caisse');

it('marque une opération de caisse justifiée seulement avec une pièce', function () {
    $caisse = compte(TypeCompte::Caisse, 5000);
    $operation = Decaissement::create([
        'type' => TypeDecaissement::OperationCaisse, 'operateur' => 'Karim', 'motif' => 'Taxi', 'date_operation' => now(),
        'compte_id' => $caisse->id, 'mode' => ModePaiement::Especes, 'montant' => 200,
    ]);

    expect($operation->justifie)->toBeFalse()
        ->and(Decaissement::caisseNonJustifiee()->count())->toBe(1);

    $operation->update(['piece_justificative' => 'justificatifs/recu.pdf']);

    expect($operation->fresh()->justifie)->toBeTrue()
        ->and(Decaissement::caisseNonJustifiee()->count())->toBe(0)
        ->and($caisse->solde())->toBe(4800.0);
});

it('calcule la prochaine période d’une charge fixe et refuse de la payer deux fois', function () {
    $banque = compte();
    $charge = ChargeFixe::create([
        'libelle' => 'Loyer', 'categorie' => CategorieCharge::Loyer, 'montant' => 10000, 'periodicite' => Periodicite::Trimestrielle,
    ]);
    $payer = fn (string $periode) => Decaissement::create([
        'type' => TypeDecaissement::ChargeFixe, 'charge_fixe_id' => $charge->id, 'periode' => $periode,
        'date_operation' => now(), 'compte_id' => $banque->id, 'mode' => ModePaiement::Virement, 'montant' => 10000,
    ]);

    expect($charge->prochainePeriode())->toBe(now()->format('Y-m'));

    $payer('2026-01');
    expect($charge->prochainePeriode())->toBe('2026-04');

    expect(fn () => $payer('2026-01'))->toThrow(ValidationException::class, 'déjà réglée');
});
