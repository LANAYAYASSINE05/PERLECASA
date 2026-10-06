<?php

namespace Database\Seeders;

use App\Enums\CategorieCharge;
use App\Enums\ModePaiement;
use App\Enums\Periodicite;
use App\Enums\Role;
use App\Enums\StatutBonCommande;
use App\Enums\TypeCompte;
use App\Enums\TypeDecaissement;
use App\Enums\TypeEncaissement;
use App\Models\Appartement;
use App\Models\BonCommande;
use App\Models\ChargeFixe;
use App\Models\Client;
use App\Models\Compte;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Models\Fournisseur;
use App\Models\Programme;
use App\Models\User;
use App\Models\Vente;
use App\Services\Alertes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/** Six mois d'activité d'une petite société de promotion, avec des opérations de caisse encore à justifier. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        Alertes::$actives = false;
        $motDePasse = config('app.demo.password');

        $comptable = User::updateOrCreate(['email' => 'comptable@perlacasa.ma'],
            ['name' => 'Salma Idrissi', 'password' => $motDePasse, 'role' => Role::Comptable, 'actif' => true]);
        $caissier = User::updateOrCreate(['email' => 'caissier@perlacasa.ma'],
            ['name' => 'Youssef Amrani', 'password' => $motDePasse, 'role' => Role::Caissier, 'actif' => true]);

        auth()->setUser($comptable);

        $banque = Compte::create(['nom' => 'Attijariwafa bank — compte courant', 'type' => TypeCompte::Banque, 'banque' => 'Attijariwafa bank', 'rib' => '007 780 0001234567890123 45', 'adresse_agence' => 'Agence Anfa, boulevard d’Anfa, Casablanca', 'solde_initial' => 800000]);
        $travaux = Compte::create(['nom' => 'BMCE — compte travaux', 'type' => TypeCompte::Banque, 'banque' => 'Bank of Africa', 'rib' => '011 780 0009876543210987 12', 'adresse_agence' => 'Agence Maârif, rue Ibnou Mounir, Casablanca', 'solde_initial' => 250000]);
        $caisse = Compte::create(['nom' => 'Caisse principale', 'type' => TypeCompte::Caisse, 'solde_initial' => 25000]);

        $appartements = $this->programmes();
        $clients = $this->clients();
        $debut = now()->startOfMonth()->subMonths(5);

        $ventes = [
            ['A101', 0, 0, 1], ['A203', 1, 0, 6], ['B102', 2, 1, 3], ['G01', 3, 1, 12],
            ['A302', 4, 2, 9], ['G04', 5, 3, 4], ['B201', 6, 4, 15],
        ];

        foreach ($ventes as [$reference, $client, $mois, $jour]) {
            $appartement = $appartements[$reference];
            $date = $debut->copy()->addMonths($mois)->addDays($jour);
            $vente = Vente::create([
                'appartement_id' => $appartement->id,
                'client_id' => $clients[$client]->id,
                'date_vente' => $date,
                'prix_vente' => $appartement->prix,
            ]);

            $tranches = [[0, 0.3, 'Avance à la réservation'], [1, 0.3, '2e tranche'], [3, 0.4, 'Solde à la livraison']];

            foreach ($tranches as [$decalage, $part, $libelle]) {
                $dateTranche = $date->copy()->addMonths($decalage);

                if ($dateTranche->isFuture()) {
                    break;
                }

                Encaissement::create([
                    'type' => TypeEncaissement::VenteAppartement,
                    'vente_id' => $vente->id,
                    'date_operation' => $dateTranche,
                    'compte_id' => $banque->id,
                    'mode' => $decalage === 0 ? ModePaiement::Cheque : ModePaiement::Virement,
                    'reference' => ($decalage === 0 ? 'CHQ ' : 'VIR ').random_int(100000, 999999),
                    'montant' => round($vente->prix_vente * $part, 2),
                    'libelle' => $libelle,
                ]);
            }
        }

        $virements = [
            [1, 8, 'Étude Me Bennani, notaire', 'Restitution de consignation', 45000, $banque],
            [2, 20, 'Wafa Assurance', 'Indemnité sinistre chantier', 18500, $travaux],
            [4, 2, 'Associé — M. Lahlou', 'Apport en compte courant d’associé', 300000, $travaux],
            [5, 10, 'Banque Populaire', 'Déblocage crédit promoteur', 500000, $travaux],
        ];

        foreach ($virements as [$mois, $jour, $emetteur, $libelle, $montant, $compte]) {
            Encaissement::create([
                'type' => TypeEncaissement::VirementRecu,
                'emetteur' => $emetteur,
                'date_operation' => $debut->copy()->addMonths($mois)->addDays($jour)->min(now()),
                'compte_id' => $compte->id,
                'mode' => ModePaiement::Virement,
                'reference' => 'VIR '.random_int(100000, 999999),
                'montant' => $montant,
                'libelle' => $libelle,
            ]);
        }

        $fournisseurs = collect([
            ['SCI Anfa Patrimoine', '001523698000045'],
            ['Lydec', '000209876000012'],
            ['Maroc Telecom', '000035624000088'],
            ['Wafa Assurance', '000047852000031'],
            ['Sotravaux BTP', '002145789000067', 'Zone industrielle Ain Sebaâ, Casablanca'],
            ['Ciments du Maroc', '000098745000023', 'Route de Rabat, km 8, Ain Harrouda'],
            ['Bureau d’études Atlas', '002589632000014', '12 rue Moussa Ibn Noussair, Casablanca'],
        ])->mapWithKeys(fn ($f) => [$f[0] => Fournisseur::create(['raison_sociale' => $f[0], 'ice' => $f[1], 'adresse' => $f[2] ?? null, 'telephone' => '05 22 '.random_int(10, 99).' '.random_int(10, 99).' '.random_int(10, 99)])]);

        $charges = [
            ['Loyer du siège', CategorieCharge::Loyer, 25000, Periodicite::Mensuelle, 'SCI Anfa Patrimoine'],
            ['Salaires du personnel', CategorieCharge::Salaires, 118000, Periodicite::Mensuelle, null],
            ['Électricité et eau', CategorieCharge::Energie, 4200, Periodicite::Mensuelle, 'Lydec'],
            ['Internet et téléphonie', CategorieCharge::Telecom, 1850, Periodicite::Mensuelle, 'Maroc Telecom'],
            ['Assurance multirisque', CategorieCharge::Assurance, 21000, Periodicite::Trimestrielle, 'Wafa Assurance'],
            ['Échéance crédit promoteur', CategorieCharge::Credit, 85000, Periodicite::Trimestrielle, null],
        ];

        foreach ($charges as $i => [$libelle, $categorie, $montant, $periodicite, $fournisseur]) {
            $charge = ChargeFixe::create([
                'libelle' => $libelle, 'categorie' => $categorie, 'montant' => $montant,
                'periodicite' => $periodicite, 'fournisseur_id' => $fournisseur ? $fournisseurs[$fournisseur]->id : null,
            ]);

            // Le mois en cours reste à payer pour les deux dernières charges mensuelles.
            $dernierMois = $i >= 2 && $i <= 3 ? 4 : 5;

            for ($mois = 0; $mois <= $dernierMois; $mois += $periodicite->enMois()) {
                $periode = $debut->copy()->addMonths($mois);

                Decaissement::create([
                    'type' => TypeDecaissement::ChargeFixe,
                    'charge_fixe_id' => $charge->id,
                    'periode' => $periode->format('Y-m'),
                    'date_operation' => $periode->copy()->addDays(4)->min(now()),
                    'compte_id' => $charge->categorie === CategorieCharge::Credit ? $travaux->id : $banque->id,
                    'mode' => $charge->categorie === CategorieCharge::Credit ? ModePaiement::Prelevement : ModePaiement::Virement,
                    'montant' => $montant,
                    'motif' => $libelle,
                ]);
            }
        }

        $factures = [
            [0, 'Sotravaux BTP', 'F-2026-118', 185000], [1, 'Ciments du Maroc', 'CM-45871', 64300],
            [1, 'Bureau d’études Atlas', 'BEA-0291', 38000], [2, 'Sotravaux BTP', 'F-2026-164', 210000],
            [3, 'Ciments du Maroc', 'CM-46902', 71850], [4, 'Sotravaux BTP', 'F-2026-207', 195500],
            [5, 'Bureau d’études Atlas', 'BEA-0334', 24000],
        ];

        foreach ($factures as [$mois, $fournisseur, $facture, $montant]) {
            Decaissement::create([
                'type' => TypeDecaissement::Fournisseur,
                'fournisseur_id' => $fournisseurs[$fournisseur]->id,
                'reference_facture' => $facture,
                'date_operation' => $debut->copy()->addMonths($mois)->addDays(18)->min(now()),
                'compte_id' => $travaux->id,
                'mode' => ModePaiement::Virement,
                'montant' => $montant,
                'motif' => "Règlement facture {$facture}",
            ]);
        }

        $this->bonsCommande($fournisseurs->all(), $debut);

        auth()->setUser($caissier);
        Storage::disk(Decaissement::DISQUE)->makeDirectory('justificatifs');

        $caisseOps = [
            [0, 6, 'Youssef Amrani', 'Station Afriquia', 'Carburant véhicule de chantier', 800, true],
            [1, 3, 'Youssef Amrani', 'Papeterie Al Amal', 'Fournitures de bureau', 1250, true],
            [2, 11, 'Karim Tazi', 'Quincaillerie Derb Omar', 'Petit matériel chantier', 2300, true],
            [3, 14, 'Youssef Amrani', null, 'Frais de timbres et légalisations', 640, true],
            [4, 2, 'Karim Tazi', 'Café Le Relais', 'Réception visite clients', 980, false],
            [4, 22, 'Youssef Amrani', 'Gardien chantier', 'Avance sur salaire', 1500, false],
            [5, 1, 'Karim Tazi', 'Taxi', 'Déplacements administration', 420, false],
            [5, 9, 'Youssef Amrani', 'Plombier indépendant', 'Réparation fuite bureau de vente', 1800, false],
            [5, 17, 'Karim Tazi', null, 'Achats divers non détaillés', 2650, false],
        ];

        foreach ($caisseOps as $i => [$mois, $jour, $operateur, $beneficiaire, $motif, $montant, $justifiee]) {
            $piece = null;

            if ($justifiee) {
                $piece = "justificatifs/demo-recu-{$i}.pdf";
                Storage::disk(Decaissement::DISQUE)->put($piece, $this->pdfMinimal("Reçu {$motif} — {$montant} MAD"));
            }

            Decaissement::create([
                'type' => TypeDecaissement::OperationCaisse,
                'operateur' => $operateur,
                'beneficiaire' => $beneficiaire,
                'date_operation' => Carbon::parse($debut)->addMonths($mois)->addDays($jour)->min(now()),
                'compte_id' => $caisse->id,
                'mode' => ModePaiement::Especes,
                'montant' => $montant,
                'motif' => $motif,
                'piece_justificative' => $piece,
            ]);
        }

        auth()->forgetUser();
        Alertes::$actives = true;
    }

    /** @param array<string, Fournisseur> $fournisseurs */
    private function bonsCommande(array $fournisseurs, Carbon $debut): void
    {
        $bons = [
            ['Sotravaux BTP', 1, 'Gros œuvre — Résidence Perla Anfa, bloc A', 'Chantier Perla Anfa', 'Virement à 30 jours', StatutBonCommande::Livre, [
                ['BET-25', 'Béton prêt à l’emploi B25 (m³)', 120, 850],
                ['FER-12', 'Acier HA Ø12 (tonne)', 8, 9800],
                ['MO-GO', 'Main-d’œuvre gros œuvre (forfait)', 1, 65000],
            ]],
            ['Ciments du Maroc', 3, 'Ciment pour dallage — Perla Garden', 'Chantier Perla Garden, Bouskoura', 'Chèque à 60 jours', StatutBonCommande::Livre, [
                ['CPJ45', 'Ciment CPJ 45 — sac de 50 kg', 600, 78],
                ['CPJ55', 'Ciment CPJ 55 — sac de 50 kg', 300, 92],
            ]],
            ['Bureau d’études Atlas', 5, 'Études techniques lot G', null, 'Virement à réception', StatutBonCommande::EnCours, [
                ['ET-STR', 'Étude de structure béton armé', 1, 28000],
                ['ET-FLU', 'Étude des lots fluides (plomberie, électricité)', 1, 16500],
                ['SUIVI', 'Suivi de chantier (visite)', 6, 1500],
            ]],
        ];

        foreach ($bons as [$fournisseur, $mois, $objet, $lieu, $conditions, $statut, $lignes]) {
            $date = $debut->copy()->addMonths($mois)->addDays(6)->min(now());
            $bon = BonCommande::create([
                'fournisseur_id' => $fournisseurs[$fournisseur]->id,
                'date_commande' => $date,
                'date_livraison' => $date->copy()->addDays(15),
                'objet' => $objet,
                'lieu_livraison' => $lieu,
                'conditions_paiement' => $conditions,
                'statut' => $statut,
            ]);

            foreach ($lignes as $i => [$reference, $designation, $quantite, $prix]) {
                $bon->lignes()->create(['reference' => $reference, 'designation' => $designation, 'quantite' => $quantite, 'prix_unitaire' => $prix, 'ordre' => $i]);
            }
        }
    }

    /** @return array<string, Appartement> */
    private function programmes(): array
    {
        $anfa = Programme::create(['nom' => 'Résidence Perla Anfa', 'ville' => 'Casablanca']);
        $garden = Programme::create(['nom' => 'Perla Garden', 'ville' => 'Bouskoura']);

        $lots = [
            [$anfa, 'A101', '2 pièces', 68, 1150000], [$anfa, 'A102', '3 pièces', 92, 1520000],
            [$anfa, 'A203', '3 pièces', 95, 1580000], [$anfa, 'A302', '4 pièces', 128, 2250000],
            [$anfa, 'A401', 'Duplex', 165, 3100000], [$anfa, 'A001', 'Local commercial', 75, 1900000],
            [$garden, 'B102', '2 pièces', 64, 780000], [$garden, 'B201', '3 pièces', 88, 990000],
            [$garden, 'B202', '3 pièces', 86, 960000], [$garden, 'G01', 'Studio', 42, 520000],
            [$garden, 'G04', '2 pièces', 61, 740000], [$garden, 'G05', '4 pièces', 118, 1350000],
        ];

        return collect($lots)->mapWithKeys(fn ($l) => [$l[1] => Appartement::create([
            'programme_id' => $l[0]->id, 'reference' => $l[1], 'typologie' => $l[2], 'surface' => $l[3], 'prix' => $l[4],
        ])])->all();
    }

    /** @return list<Client> */
    private function clients(): array
    {
        return collect([
            ['El Amrani', 'Hicham', 'BE458712', '06 61 23 45 67', 'Résidence Les Palmiers, bd Ghandi, Casablanca'],
            ['Benjelloun', 'Nadia', 'BK102938', '06 62 78 90 12', '45 rue Ahmed Charci, Maârif, Casablanca'],
            ['Chraibi', 'Omar', 'BH784512', '06 70 11 22 33', 'Villa 12, lotissement Californie, Casablanca'],
            ['Alaoui', 'Sara', 'BJ336699', '06 66 44 55 66', 'Appt 8, imm. 3, Hay Riad, Rabat'],
            ['Berrada', 'Mehdi', 'BE998877', '06 61 98 76 54', '22 avenue Hassan II, Mohammedia'],
            ['Fassi Fihri', 'Leila', 'BL123321', '06 63 25 36 47', 'Résidence Atlas, Bouskoura'],
            ['Kettani', 'Anas', 'BK556644', '06 64 14 25 36', '17 rue de Fès, Racine, Casablanca'],
            ['Squalli', 'Imane', 'BH221144', '06 65 74 85 96', 'Bd Zerktouni, Gauthier, Casablanca'],
        ])->map(fn ($c) => Client::create([
            'nom' => $c[0], 'prenom' => $c[1], 'cin' => $c[2], 'telephone' => $c[3], 'adresse' => $c[4],
            'email' => strtolower(str_replace(' ', '', $c[1]).'.'.str_replace(' ', '', $c[0])).'@exemple.ma',
        ]))->all();
    }

    private function pdfMinimal(string $texte): string
    {
        $texte = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], iconv('UTF-8', 'Windows-1252//TRANSLIT', $texte));
        $flux = "BT /F1 14 Tf 72 760 Td ({$texte}) Tj ET";

        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
            .'4 0 obj<</Length '.strlen($flux).">>stream\n{$flux}\nendstream endobj\n"
            ."5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    }
}
