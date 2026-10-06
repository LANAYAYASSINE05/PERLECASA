<?php

namespace App\Services;

use App\Enums\ModePaiement;
use App\Enums\TypeCompte;
use App\Enums\TypeEncaissement;
use App\Models\BonCommande;
use App\Models\Compte;
use App\Models\Encaissement;
use App\Models\LigneBonCommande;
use App\Models\Vente;
use App\Support\MontantEnLettres;

/**
 * Contenu de la facture, du reçu et du bon de commande, structuré comme le modèle
 * Excel « Facture Proforma » : en-tête, cadre du tiers, tableau, totaux, pied. Rendu par PiecesExcel.
 */
class PiecesCommerciales
{
    public static function facture(Vente $vente): array
    {
        $vente->loadMissing('appartement.programme', 'client');
        $appartement = $vente->appartement;
        $client = $vente->client;
        $taux = (float) config('societe.tva_vente');
        $ttc = (float) $vente->prix_vente;
        $ht = round($ttc / (1 + $taux / 100), 2);
        $encaisse = $vente->encaisse();

        return [
            'titre' => 'Facture',
            'numero' => $vente->numeroFacture(),
            'infos' => [
                'Date' => $vente->date_vente->format('d/m/Y'),
                'Réf Vente' => $vente->numero,
                'Nature' => "Vente d'appartement",
                'Programme' => trim("{$appartement->programme?->nom} {$appartement->programme?->ville}"),
            ],
            'tiers' => [
                'code' => 'Code Client : '.$client->code(),
                'nom' => $client->nomComplet(),
                'lignes' => array_filter([
                    $client->ice ? 'ICE : '.$client->ice : null,
                    $client->cin ? 'CIN : '.$client->cin : null,
                    $client->adresse,
                    $client->telephone ? 'Tél : '.$client->telephone : null,
                ]),
            ],
            'colonnes' => self::colonnesHt(),
            'sections' => [[
                'titre' => "Contrat de vente N° {$vente->numero} du {$vente->date_vente->format('d/m/Y')}",
                'lignes' => [[
                    $appartement->reference,
                    trim("Appartement {$appartement->typologie} · {$appartement->surface} m² · {$appartement->programme?->nom}"),
                    self::valeur(1),
                    self::valeur($ht),
                    self::valeur($ht),
                ]],
            ]],
            'arrete' => 'Arrêtée la présente facture à la somme de :',
            'lettres' => MontantEnLettres::dirhams($ttc),
            'totaux' => [
                ['Total H.T', self::valeur($ht)],
                ["Total T.V.A ({$taux} %)", self::valeur($ttc - $ht)],
                ['Total T.T.C', self::valeur($ttc)],
            ],
            'conditions' => [
                'Déjà encaissé' => self::nombre($encaisse).' MAD',
                'Reste dû' => self::nombre(max(0, $ttc - $encaisse)).' MAD',
            ],
            'banque' => self::banque(Compte::query()->where('type', TypeCompte::Banque)->where('actif', true)->orderBy('id')->first()),
            'signatures' => [],
        ];
    }

    public static function recu(Encaissement $encaissement): array
    {
        $encaissement->loadMissing('vente.client', 'vente.appartement', 'compte');
        $vente = $encaissement->vente;
        $client = $vente?->client;
        $montant = (float) $encaissement->montant;

        $tiers = $client
            ? [
                'code' => 'Code Client : '.$client->code(),
                'nom' => $client->nomComplet(),
                'lignes' => array_filter([
                    $client->ice ? 'ICE : '.$client->ice : null,
                    $client->cin ? 'CIN : '.$client->cin : null,
                    $client->adresse,
                ]),
            ]
            : ['code' => 'Donneur d\'ordre', 'nom' => (string) $encaissement->emetteur, 'lignes' => []];

        $description = $encaissement->type === TypeEncaissement::VenteAppartement
            ? "Règlement de l'appartement {$vente?->appartement?->reference} · vente {$vente?->numero}"
            : ($encaissement->libelle ?: 'Virement reçu');

        $conditions = ['Mode de paiement' => $encaissement->mode?->getLabel()];

        if ($vente) {
            $conditions['Reste dû sur la vente'] = self::nombre(max(0, $vente->resteDu())).' MAD';
        }

        return [
            'titre' => $encaissement->mode === ModePaiement::Virement ? 'Reçu de virement' : 'Reçu de paiement',
            'numero' => $encaissement->numero,
            'infos' => array_filter([
                'Date' => $encaissement->date_operation->format('d/m/Y'),
                'Réf Opération' => $encaissement->reference,
                'Nature' => $encaissement->type->getLabel(),
                'Réf Vente' => $vente?->numero,
            ]),
            'tiers' => $tiers,
            'colonnes' => [
                ['Référence', 'gauche', 16],
                ['Description', 'gauche', 52],
                ['Mode', 'centre', 14],
                ['Montant', 'droite', 18],
            ],
            'sections' => [[
                'titre' => $encaissement->type === TypeEncaissement::VenteAppartement
                    ? "Contrat de vente N° {$vente?->numero}"
                    : 'Virement reçu le '.$encaissement->date_operation->format('d/m/Y'),
                'lignes' => [[
                    (string) $encaissement->reference,
                    $description,
                    (string) $encaissement->mode?->getLabel(),
                    self::valeur($montant),
                ]],
            ]],
            'arrete' => 'Arrêté le présent reçu à la somme de :',
            'lettres' => MontantEnLettres::dirhams($montant),
            'totaux' => [['Montant reçu', self::valeur($montant)]],
            'conditions' => $conditions,
            'banque' => self::banque($encaissement->compte, 'Compte crédité'),
            'signatures' => ['Le client', 'Service Finance'],
        ];
    }

    public static function bonCommande(BonCommande $bon): array
    {
        $bon->loadMissing('fournisseur', 'lignes');
        $fournisseur = $bon->fournisseur;
        $taux = (float) $bon->taux_tva;

        return [
            'titre' => 'Bon de commande',
            'numero' => $bon->numero,
            'infos' => array_filter([
                'Date' => $bon->date_commande->format('d/m/Y'),
                'Objet' => $bon->objet,
                'Livraison' => $bon->date_livraison?->format('d/m/Y'),
                'Lieu' => $bon->lieu_livraison,
            ]),
            'tiers' => [
                'code' => 'Code Fournisseur : '.$fournisseur->code(),
                'nom' => $fournisseur->raison_sociale,
                'lignes' => array_filter([
                    $fournisseur->ice ? 'ICE : '.$fournisseur->ice : null,
                    $fournisseur->adresse,
                    $fournisseur->telephone ? 'Tél : '.$fournisseur->telephone : null,
                ]),
            ],
            'colonnes' => self::colonnesHt(),
            'sections' => [[
                'titre' => $bon->objet ?: "Commande N° {$bon->numero}",
                'lignes' => $bon->lignes->map(fn (LigneBonCommande $l) => [
                    (string) $l->reference,
                    $l->designation,
                    self::valeur((float) $l->quantite),
                    self::valeur((float) $l->prix_unitaire),
                    self::valeur($l->montant()),
                ])->all(),
            ]],
            'arrete' => 'Arrêté le présent bon de commande à la somme de :',
            'lettres' => MontantEnLettres::dirhams($bon->totalTtc()),
            'totaux' => [
                ['Total H.T', self::valeur($bon->totalHt())],
                ["Total T.V.A ({$taux} %)", self::valeur($bon->totalTva())],
                ['Total T.T.C', self::valeur($bon->totalTtc())],
            ],
            'conditions' => array_filter([
                'Conditions de paiement' => $bon->conditions_paiement,
                'Statut' => $bon->statut->getLabel(),
            ]),
            'banque' => [],
            'signatures' => ['Le fournisseur', self::raisonSociale()],
        ];
    }

    private static function colonnesHt(): array
    {
        return [
            ['Référence', 'gauche', 14],
            ['Description', 'gauche', 44],
            ['Quantité', 'droite', 10],
            ['PU HT', 'droite', 15],
            ['Montant HT', 'droite', 17],
        ];
    }

    private static function banque(?Compte $compte, string $titre = 'Règlement'): array
    {
        return array_filter([
            'Société' => self::raisonSociale(),
            'Banque' => $compte?->banque ?: $compte?->nom,
            'Adresse' => $compte?->adresse_agence,
            'Numéro de compte' => $compte?->rib,
        ]) + ['_titre' => $titre];
    }

    private static function raisonSociale(): string
    {
        return trim(config('societe.nom').' '.config('societe.forme'));
    }

    private static function nombre(float $valeur): string
    {
        return number_format($valeur, 2, ',', ' ');
    }

    /** Les montants du tableau et des totaux restent numériques pour être écrits comme nombres dans Excel. */
    private static function valeur(float $valeur): float
    {
        return round($valeur, 2);
    }
}
