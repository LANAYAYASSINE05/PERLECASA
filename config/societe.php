<?php

/* Identité de Perle Casa Immobilier imprimée sur les factures, reçus et bons de commande. */
return [
    'nom' => env('SOCIETE_NOM', 'Perle Casa Immobilier'),
    'forme' => env('SOCIETE_FORME', 'SARL'),
    'capital' => (float) env('SOCIETE_CAPITAL', 0),
    'adresse' => env('SOCIETE_ADRESSE', ''),
    'ville' => env('SOCIETE_VILLE', 'Casablanca'),
    'telephone' => env('SOCIETE_TELEPHONE', ''),
    'email' => env('SOCIETE_EMAIL', ''),
    'ice' => env('SOCIETE_ICE', ''),
    'rc' => env('SOCIETE_RC', ''),
    'if' => env('SOCIETE_IF', ''),
    'patente' => env('SOCIETE_PATENTE', ''),
    'cnss' => env('SOCIETE_CNSS', ''),

    /* Le prix de vente d'un appartement est saisi TTC ; la facture en déduit le HT avec ce taux. */
    'tva_vente' => (float) env('TVA_VENTE', 20),

    'echeance_jours' => (int) env('ECHEANCE_JOURS', 30),
];
