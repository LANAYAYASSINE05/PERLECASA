<?php

namespace App\Services;

use App\Enums\TypeCompte;
use App\Enums\TypeDecaissement;
use App\Enums\TypeEncaissement;
use App\Models\Compte;
use App\Models\Decaissement;
use App\Models\Vente;
use App\Support\Montant;
use BackedEnum;

/**
 * Règles de gestion des mouvements d'argent, partagées par les formulaires et les modèles.
 * Chaque méthode renvoie les erreurs par champ ; un tableau vide veut dire que l'opération est permise.
 */
class ControleFinance
{
    /** @return array<string, string> */
    public static function encaissement(array $d, ?int $ignorer = null): array
    {
        $erreurs = [];
        $montant = (float) ($d['montant'] ?? 0);

        if ($montant <= 0) {
            $erreurs['montant'] = 'Le montant doit être supérieur à zéro.';
        }

        if (blank($d['compte_id'] ?? null)) {
            $erreurs['compte_id'] = 'Choisissez le compte qui reçoit l’argent.';
        }

        $type = TypeEncaissement::tryFrom((string) self::valeur($d['type'] ?? null));

        if ($type === TypeEncaissement::VenteAppartement) {
            $vente = Vente::find($d['vente_id'] ?? null);

            if (! $vente) {
                $erreurs['vente_id'] = 'Choisissez la vente de l’appartement concerné.';
            } elseif ($montant > $vente->resteDu($ignorer) + 0.001) {
                $erreurs['montant'] = 'Le montant dépasse le reste dû de la vente ('.self::mad($vente->resteDu($ignorer)).').';
            }
        }

        if ($type === TypeEncaissement::VirementRecu && blank($d['emetteur'] ?? null)) {
            $erreurs['emetteur'] = 'Indiquez qui a fait le virement.';
        }

        return $erreurs;
    }

    /** @return array<string, string> */
    public static function decaissement(array $d, ?int $ignorer = null): array
    {
        $erreurs = [];
        $montant = (float) ($d['montant'] ?? 0);
        $type = TypeDecaissement::tryFrom((string) self::valeur($d['type'] ?? null));
        $compte = Compte::find($d['compte_id'] ?? null);

        if ($montant <= 0) {
            $erreurs['montant'] = 'Le montant doit être supérieur à zéro.';
        }

        if ($type === TypeDecaissement::ChargeFixe) {
            $periode = (string) ($d['periode'] ?? '');

            if (blank($d['charge_fixe_id'] ?? null)) {
                $erreurs['charge_fixe_id'] = 'Choisissez la charge fixe réglée.';
            }

            if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode)) {
                $erreurs['periode'] = 'Indiquez la période réglée au format AAAA-MM.';
            } elseif (filled($d['charge_fixe_id'] ?? null) && Decaissement::query()
                ->where('charge_fixe_id', $d['charge_fixe_id'])
                ->where('periode', $periode)
                ->when($ignorer, fn ($q) => $q->whereKeyNot($ignorer))
                ->exists()) {
                $erreurs['periode'] = "La période {$periode} de cette charge est déjà réglée.";
            }
        }

        if ($type === TypeDecaissement::Fournisseur && blank($d['fournisseur_id'] ?? null)) {
            $erreurs['fournisseur_id'] = 'Choisissez le fournisseur payé.';
        }

        if ($type === TypeDecaissement::OperationCaisse) {
            if (blank($d['operateur'] ?? null)) {
                $erreurs['operateur'] = 'Indiquez l’opérateur de caisse.';
            }

            if ($compte && $compte->type !== TypeCompte::Caisse) {
                $erreurs['compte_id'] = 'Une opération de caisse sort forcément d’un compte de caisse.';
            }
        }

        if (! $compte) {
            $erreurs['compte_id'] ??= 'Choisissez le compte qui paie.';
        } elseif ($montant > 0 && $montant > $compte->solde($ignorer) + 0.001) {
            $erreurs['montant'] ??= "Solde insuffisant sur « {$compte->nom} » (".self::mad($compte->solde($ignorer)).' disponibles).';
        }

        return $erreurs;
    }

    private static function mad(float $montant): string
    {
        return Montant::mad($montant);
    }

    private static function valeur(mixed $v): mixed
    {
        return $v instanceof BackedEnum ? $v->value : $v;
    }
}
