<?php

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Enums\TypeEncaissement;
use App\Filament\Resources\BonCommandeResource\Pages\ManageBonsCommande;
use App\Models\BonCommande;
use App\Models\Encaissement;
use App\Models\Fournisseur;
use App\Services\PiecesCommerciales;
use App\Services\PiecesExcel;
use App\Support\MontantEnLettres;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

function bonCommande(): BonCommande
{
    $bon = BonCommande::create([
        'fournisseur_id' => Fournisseur::create(['raison_sociale' => 'Sotravaux BTP', 'ice' => '002145789000067'])->id,
        'date_commande' => now(),
        'objet' => 'Gros œuvre',
    ]);
    $bon->lignes()->createMany([
        ['designation' => 'Béton B25 (m³)', 'quantite' => 10, 'prix_unitaire' => 850, 'ordre' => 0],
        ['designation' => 'Acier HA Ø12 (tonne)', 'quantite' => 1.5, 'prix_unitaire' => 9800, 'ordre' => 1],
    ]);

    return $bon;
}

it('écrit les montants en lettres comme sur le modèle Excel', function (float $montant, string $attendu) {
    expect(MontantEnLettres::dirhams($montant))->toBe($attendu);
})->with([
    [138, 'CENT TRENTE-HUIT DIRHAMS'],
    [1, 'UN DIRHAM'],
    [1250000.5, 'UN MILLION DEUX CENT CINQUANTE MILLE DIRHAMS ET CINQUANTE CENTIMES'],
]);

it('calcule les totaux du bon de commande', function () {
    $bon = bonCommande();

    expect($bon->numero)->toStartWith('BC-')
        ->and($bon->totalHt())->toBe(23200.0)
        ->and($bon->totalTva())->toBe(4640.0)
        ->and($bon->totalTtc())->toBe(27840.0);
});

it('décompose le prix TTC de la vente sur la facture et fige son numéro', function () {
    $vente = vente(1200000);

    $piece = PiecesCommerciales::facture($vente);
    $numero = $vente->fresh()->numero_facture;

    expect($piece['numero'])->toStartWith('FAC-')->toBe($numero)
        ->and($piece['totaux'][0][1])->toBe(1000000.0)
        ->and($piece['totaux'][2][1])->toBe(1200000.0)
        ->and($piece['lettres'])->toBe('UN MILLION DEUX CENT MILLE DIRHAMS')
        ->and(PiecesCommerciales::facture($vente->fresh())['numero'])->toBe($numero);
});

it('télécharge la facture, le reçu et le bon de commande au format Excel', function () {
    $vente = vente(500000);
    $encaissement = Encaissement::create([
        'type' => TypeEncaissement::VenteAppartement, 'vente_id' => $vente->id, 'date_operation' => now(),
        'compte_id' => compte()->id, 'mode' => ModePaiement::Virement, 'reference' => 'VIR-001', 'montant' => 100000,
    ]);
    $comptable = utilisateur();

    foreach ([route('ventes.facture', $vente), route('encaissements.recu', $encaissement), route('bons-commande.imprimer', bonCommande())] as $url) {
        $this->actingAs($comptable)->get($url)
            ->assertOk()
            ->assertDownload()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
});

/** Cellule voisine (même ligne) de la première cellule contenant $texte. */
function aDroiteDe(Worksheet $feuille, string $texte, string $colonne): mixed
{
    foreach ($feuille->getRowIterator() as $ligne) {
        foreach ($ligne->getCellIterator() as $cellule) {
            if ($cellule->getValue() === $texte) {
                return $feuille->getCell($colonne.$cellule->getRow())->getValue();
            }
        }
    }

    return null;
}

it('remplit la feuille Excel aux couleurs de l’application avec de vrais nombres', function () {
    $feuille = PiecesExcel::classeur(PiecesCommerciales::bonCommande(bonCommande()))->getActiveSheet();
    $valeurs = collect($feuille->toArray(null, false, false))->flatten()->filter()->values();

    expect($valeurs)->toContain('BON DE COMMANDE', 'Sotravaux BTP', 'Gros œuvre', 'VINGT-SEPT MILLE HUIT CENT QUARANTE DIRHAMS')
        ->and($valeurs->first(fn ($v) => is_string($v) && str_starts_with($v, 'N° BC-')))->not->toBeNull()
        ->and(aDroiteDe($feuille, 'Béton B25 (m³)', 'F'))->toBe(8500.0)
        ->and(aDroiteDe($feuille, 'Total T.T.C', 'F'))->toBe(27840.0)
        ->and($feuille->getShowGridlines())->toBeFalse()
        ->and($feuille->getStyle('B1')->getFont()->getName())->toBe('Segoe UI');
});

it('allonge le tableau Excel quand la commande est longue', function () {
    $bon = bonCommande();
    foreach (range(1, 30) as $i) {
        $bon->lignes()->create(['designation' => "Article {$i}", 'quantite' => 1, 'prix_unitaire' => 10, 'ordre' => $i + 1]);
    }

    $feuille = PiecesExcel::classeur(PiecesCommerciales::bonCommande($bon->fresh()))->getActiveSheet();

    expect(aDroiteDe($feuille, 'Article 30', 'F'))->toBe(10.0)
        ->and(aDroiteDe($feuille, 'Total T.T.C', 'F'))->toBe(28200.0);
});

it('refuse les pièces commerciales à l’opérateur de caisse', function () {
    $caissier = utilisateur(Role::Caissier);

    $this->actingAs($caissier)->get(route('ventes.facture', vente()))->assertForbidden();
    $this->actingAs($caissier)->get(route('bons-commande.imprimer', bonCommande()))->assertForbidden();
    $this->actingAs($caissier)->get('/bons-commande')->assertForbidden();
});

it('crée un bon de commande avec ses lignes depuis l’écran', function () {
    $fournisseur = Fournisseur::create(['raison_sociale' => 'Ciments du Maroc']);
    $this->actingAs(utilisateur());
    $annulerFake = Repeater::fake();

    Livewire::test(ManageBonsCommande::class)
        ->callAction('create', [
            'fournisseur_id' => $fournisseur->id,
            'date_commande' => now()->format('Y-m-d'),
            'taux_tva' => 20,
            'statut' => 'en_cours',
            'lignes' => [
                ['reference' => 'CPJ45', 'designation' => 'Ciment CPJ 45', 'quantite' => 100, 'prix_unitaire' => 78],
            ],
        ])
        ->assertHasNoActionErrors();
    $annulerFake();

    $bon = BonCommande::sole();
    expect($bon->lignes)->toHaveCount(1)
        ->and($bon->totalTtc())->toBe(9360.0);
});
