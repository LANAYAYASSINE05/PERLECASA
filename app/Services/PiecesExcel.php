<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Feuille Excel d'une pièce commerciale (facture, reçu, bon de commande) aux couleurs
 * de l'application : encre marine, repères or, fond papier. Colonnes utiles B à F.
 */
class PiecesExcel
{
    private const MARINE = '0C2A4D';

    private const PLAN = '1F4F86';

    private const OR = 'C4953A';

    private const OR_CLAIR = 'E2C27A';

    private const PAPIER = 'F5F8FB';

    private const BORD = 'D3DCE8';

    private const BORD_FORT = '9FB1C7';

    private const GRIS = '64748B';

    private const TITRE = 'Georgia';

    private const TEXTE = 'Segoe UI';

    private const FORMAT_MONTANT = '#,##0.00';

    public static function telecharger(array $piece): StreamedResponse
    {
        $classeur = self::classeur($piece);

        return response()->streamDownload(
            fn () => IOFactory::createWriter($classeur, 'Xlsx')->save('php://output'),
            str($piece['titre'].' '.$piece['numero'])->slug()->append('.xlsx'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public static function classeur(array $piece): Spreadsheet
    {
        $classeur = new Spreadsheet;
        $classeur->getProperties()
            ->setTitle("{$piece['titre']} N° {$piece['numero']}")
            ->setCreator(self::raisonSociale())
            ->setCompany(self::raisonSociale());
        $classeur->getDefaultStyle()->getFont()->setName(self::TEXTE)->setSize(9)->getColor()->setRGB('1E293B');
        $classeur->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $f = $classeur->getActiveSheet();
        $f->setTitle(mb_substr($piece['titre'], 0, 31));
        $f->setShowGridlines(false);

        foreach (['A' => 2, 'B' => 18, 'C' => 36, 'D' => 10, 'E' => 18, 'F' => 26, 'G' => 2] as $colonne => $largeur) {
            $f->getColumnDimension($colonne)->setWidth($largeur);
        }

        $r = self::entete($f);
        $r = self::titre($f, $piece, $r + 1);
        $r = self::infosEtTiers($f, $piece, $r + 1);
        $r = self::tableau($f, $piece, $r + 1);
        $r = self::totaux($f, $piece, $r + 1);
        $r = self::banque($f, $piece, $r + 1);
        $r = self::signatures($f, $piece, $r + 1);

        self::miseEnPage($f, $r);

        return $classeur;
    }

    /** Logo à gauche, identité de la société à droite, filet or dessous. */
    private static function entete(Worksheet $f): int
    {
        foreach ([1 => 22, 2 => 16, 3 => 14, 4 => 14] as $ligne => $hauteur) {
            $f->getRowDimension($ligne)->setRowHeight($hauteur);
        }

        if (is_file($logo = public_path('images/logo.png'))) {
            $dessin = new Drawing;
            $dessin->setPath($logo)->setHeight(62)->setCoordinates('B1')->setOffsetY(4)->setWorksheet($f);
        }

        $s = config('societe');
        $lignes = [
            [self::raisonSociale(), 13, true, self::MARINE, self::TITRE],
            [$s['capital'] > 0 ? 'Capital : '.number_format($s['capital'], 2, ',', ' ').' DH' : null, 8, false, self::GRIS, self::TEXTE],
            [$s['adresse'] ? $s['adresse'].($s['ville'] ? ', '.$s['ville'] : '') : $s['ville'], 8, false, self::GRIS, self::TEXTE],
            [collect([$s['telephone'] ? 'Tél : '.$s['telephone'] : null, $s['email'] ?: null])->filter()->implode(' · ') ?: null, 8, false, self::GRIS, self::TEXTE],
        ];

        foreach ($lignes as $i => [$texte, $taille, $gras, $couleur, $police]) {
            $ligne = $i + 1;
            $f->mergeCells("D{$ligne}:F{$ligne}");
            $f->setCellValue("D{$ligne}", $texte);
            $f->getStyle("D{$ligne}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $f->getStyle("D{$ligne}")->getFont()->setName($police)->setSize($taille)->setBold($gras)->getColor()->setRGB($couleur);
        }

        $f->getRowDimension(5)->setRowHeight(6);
        self::filet($f, 'B5:F5', self::OR, Border::BORDER_MEDIUM);

        return 5;
    }

    private static function titre(Worksheet $f, array $piece, int $r): int
    {
        $f->getRowDimension($r)->setRowHeight(8);
        $r++;
        $f->getRowDimension($r)->setRowHeight(30);

        $f->mergeCells("B{$r}:C{$r}");
        $f->setCellValue("B{$r}", mb_strtoupper($piece['titre']));
        $f->getStyle("B{$r}")->getFont()->setName(self::TITRE)->setSize(20)->setBold(true)->getColor()->setRGB(self::MARINE);

        $f->mergeCells("D{$r}:F{$r}");
        $f->setCellValue("D{$r}", 'N° '.$piece['numero']);
        $f->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $f->getStyle("D{$r}")->getFont()->setName(self::TITRE)->setSize(14)->setBold(true)->getColor()->setRGB(self::OR);

        return $r;
    }

    /** Bloc « Informations » à gauche, cadre du client ou du fournisseur à droite. */
    private static function infosEtTiers(Worksheet $f, array $piece, int $r): int
    {
        $f->getRowDimension($r)->setRowHeight(8);
        $debut = $r + 1;
        $tiers = $piece['tiers'];
        [$typeTiers, $code] = array_pad(array_map('trim', explode(':', $tiers['code'], 2)), 2, '');

        self::bandeau($f, "B{$debut}:C{$debut}", 'INFORMATIONS');
        self::bandeau($f, "D{$debut}:F{$debut}", mb_strtoupper(str_replace('Code ', '', $typeTiers)));

        $ligne = $debut + 1;
        foreach ($piece['infos'] as $libelle => $valeur) {
            $f->setCellValue("B{$ligne}", $libelle);
            $f->setCellValue("C{$ligne}", $valeur);
            $f->getStyle("B{$ligne}")->getFont()->setSize(8)->getColor()->setRGB(self::GRIS);
            $f->getStyle("C{$ligne}")->getFont()->setBold(true)->getColor()->setRGB(self::MARINE);
            $ligne++;
        }
        $finInfos = $ligne - 1;

        $lignesTiers = array_values(array_filter([$code ? "Code : {$code}" : null, ...$tiers['lignes']]));
        $ligne = $debut + 1;
        $f->mergeCells("D{$ligne}:F{$ligne}");
        $f->setCellValue("D{$ligne}", $tiers['nom']);
        $f->getStyle("D{$ligne}")->getFont()->setName(self::TITRE)->setSize(12)->setBold(true)->getColor()->setRGB(self::MARINE);
        foreach ($lignesTiers as $texte) {
            $ligne++;
            $f->mergeCells("D{$ligne}:F{$ligne}");
            $f->setCellValue("D{$ligne}", $texte);
            $f->getStyle("D{$ligne}")->getFont()->setSize(8.5)->getColor()->setRGB('334155');
        }

        $fin = max($finInfos, $ligne);

        for ($l = $debut + 1; $l <= $fin; $l++) {
            $f->getRowDimension($l)->setRowHeight(16);
        }

        self::cadre($f, "B{$debut}:C{$fin}");
        self::cadre($f, "D{$debut}:F{$fin}");
        $f->getStyle('B'.($debut + 1).":C{$fin}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::PAPIER);

        return $fin;
    }

    private static function tableau(Worksheet $f, array $piece, int $r): int
    {
        $f->getRowDimension($r)->setRowHeight(10);
        $r++;
        $plages = count($piece['colonnes']) === 5 ? ['B', 'C', 'D', 'E', 'F'] : ['B', 'C:D', 'E', 'F'];
        $alignements = ['gauche' => Alignment::HORIZONTAL_LEFT, 'centre' => Alignment::HORIZONTAL_CENTER, 'droite' => Alignment::HORIZONTAL_RIGHT];

        foreach ($piece['colonnes'] as $i => [$libelle, $alignement]) {
            $cellule = self::cellule($f, $plages[$i], $r);
            $f->setCellValue($cellule, mb_strtoupper($libelle));
            $f->getStyle($cellule)->getAlignment()->setHorizontal($alignements[$alignement]);
        }

        $entete = $f->getStyle("B{$r}:F{$r}");
        $entete->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::MARINE);
        $entete->getFont()->setBold(true)->setSize(8)->getColor()->setRGB('FFFFFF');
        $entete->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::OR);
        $f->getRowDimension($r)->setRowHeight(22);
        $debut = $r;

        foreach ($piece['sections'] as $section) {
            $r++;
            $f->mergeCells("B{$r}:F{$r}");
            $f->setCellValue("B{$r}", $section['titre']);
            $f->getStyle("B{$r}")->getFont()->setName(self::TITRE)->setBold(true)->setSize(10)->getColor()->setRGB(self::PLAN);
            $f->getStyle("B{$r}:F{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::PAPIER);
            $f->getRowDimension($r)->setRowHeight(20);

            foreach ($section['lignes'] as $n => $ligne) {
                $r++;
                foreach (array_values($ligne) as $i => $valeur) {
                    $cellule = self::cellule($f, $plages[$i], $r);
                    $f->setCellValue($cellule, $valeur === '' ? null : $valeur);
                    $style = $f->getStyle($cellule);
                    $style->getAlignment()->setHorizontal($alignements[$piece['colonnes'][$i][1]])->setWrapText($i === 1);

                    if (is_float($valeur)) {
                        $style->getNumberFormat()->setFormatCode(self::FORMAT_MONTANT);
                    }
                }
                $description = (string) (array_values($ligne)[1] ?? '');
                $f->getRowDimension($r)->setRowHeight(max(18, 12 * (int) ceil(mb_strlen($description) / 48) + 6));

                if ($n % 2 === 1) {
                    $f->getStyle("B{$r}:F{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FAFBFD');
                }
            }
        }

        // Quelques lignes vides gardent l'aspect d'un bordereau quand la pièce est courte.
        for ($vide = $r - $debut; $vide < 8; $vide++) {
            $r++;
            foreach ($plages as $plage) {
                self::cellule($f, $plage, $r);
            }
            $f->getRowDimension($r)->setRowHeight(18);
        }

        $corps = $f->getStyle('B'.($debut + 1).":F{$r}");
        $corps->getBorders()->getInside()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(self::BORD);
        $corps->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::MARINE);
        self::cadre($f, "B{$debut}:F{$r}", self::MARINE);

        return $r;
    }

    /** Montant en lettres et conditions à gauche, totaux à droite avec le dernier en marine et or. */
    private static function totaux(Worksheet $f, array $piece, int $r): int
    {
        $f->getRowDimension($r)->setRowHeight(10);
        $debut = $r + 1;

        $f->mergeCells("B{$debut}:C{$debut}");
        $f->setCellValue("B{$debut}", $piece['arrete']);
        $f->getStyle("B{$debut}")->getFont()->setItalic(true)->setSize(8)->getColor()->setRGB(self::GRIS);

        $lettres = $debut + 1;
        $f->mergeCells("B{$lettres}:C".($lettres + 1));
        $f->setCellValue("B{$lettres}", $piece['lettres']);
        $f->getStyle("B{$lettres}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $f->getStyle("B{$lettres}")->getFont()->setName(self::TITRE)->setBold(true)->setSize(10)->getColor()->setRGB(self::MARINE);

        $ligne = $debut;
        $totaux = array_values($piece['totaux']);
        foreach ($totaux as $i => [$libelle, $montant]) {
            $f->setCellValue("E{$ligne}", $libelle);
            $f->setCellValue("F{$ligne}", $montant);
            $f->getStyle("F{$ligne}")->getNumberFormat()->setFormatCode(self::FORMAT_MONTANT.' "MAD"');
            $f->getStyle("F{$ligne}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $f->getRowDimension($ligne)->setRowHeight(20);
            $style = $f->getStyle("E{$ligne}:F{$ligne}");
            $style->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(self::BORD_FORT);

            if ($i === count($totaux) - 1) {
                $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::MARINE);
                $f->getStyle("E{$ligne}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $f->getStyle("F{$ligne}")->getFont()->setName(self::TITRE)->setBold(true)->setSize(11)->getColor()->setRGB(self::OR_CLAIR);
                $f->getRowDimension($ligne)->setRowHeight(24);
            } else {
                $f->getStyle("E{$ligne}")->getFont()->getColor()->setRGB(self::GRIS);
                $f->getStyle("F{$ligne}")->getFont()->setBold(true)->getColor()->setRGB(self::MARINE);
            }
            $ligne++;
        }

        $fin = max($ligne - 1, $lettres + 1);

        if ($piece['conditions']) {
            $fin++;
            $f->getRowDimension($fin)->setRowHeight(8);
            $fin++;
            $debutConditions = $fin;
            foreach ($piece['conditions'] as $libelle => $valeur) {
                $f->setCellValue("B{$fin}", $libelle);
                $f->mergeCells("C{$fin}:F{$fin}");
                $f->setCellValue("C{$fin}", $valeur);
                $f->getStyle("B{$fin}")->getFont()->setSize(8)->getColor()->setRGB(self::GRIS);
                $f->getStyle("C{$fin}")->getFont()->setBold(true)->getColor()->setRGB(self::MARINE);
                $f->getRowDimension($fin)->setRowHeight(16);
                $fin++;
            }
            $fin--;
            $f->getStyle("B{$debutConditions}:F{$fin}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::PAPIER);
            $f->getStyle("B{$debutConditions}:B{$fin}")->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THICK)->getColor()->setRGB(self::OR);
        }

        return $fin;
    }

    private static function banque(Worksheet $f, array $piece, int $r): int
    {
        $banque = $piece['banque'];
        $titre = $banque['_titre'] ?? 'Règlement';
        unset($banque['_titre']);

        if (! $banque) {
            return $r - 1;
        }

        $f->getRowDimension($r)->setRowHeight(10);
        $r++;
        self::bandeau($f, "B{$r}:F{$r}", mb_strtoupper($titre));
        $debut = $r;

        foreach ($banque as $libelle => $valeur) {
            $r++;
            $f->setCellValue("B{$r}", $libelle);
            $f->mergeCells("C{$r}:F{$r}");
            $f->setCellValue("C{$r}", $valeur);
            $f->getStyle("B{$r}")->getFont()->setSize(8)->getColor()->setRGB(self::GRIS);
            $f->getStyle("C{$r}")->getFont()->getColor()->setRGB(self::MARINE);
            $f->getRowDimension($r)->setRowHeight(16);
        }

        self::cadre($f, "B{$debut}:F{$r}");

        return $r;
    }

    private static function signatures(Worksheet $f, array $piece, int $r): int
    {
        if (! $piece['signatures']) {
            return $r - 1;
        }

        [$gauche, $droite] = array_pad($piece['signatures'], 2, '');
        $f->getRowDimension($r)->setRowHeight(12);
        $r++;

        $f->mergeCells("B{$r}:C{$r}");
        $f->mergeCells("E{$r}:F{$r}");
        $f->setCellValue("B{$r}", mb_strtoupper("Signature · {$gauche}"));
        $f->setCellValue("E{$r}", mb_strtoupper("Signature · {$droite}"));
        $f->getStyle("B{$r}:F{$r}")->getFont()->setBold(true)->setSize(7.5)->getColor()->setRGB(self::PLAN);
        $f->getStyle("B{$r}:F{$r}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::OR);
        $f->getStyle("D{$r}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_NONE);

        $zone = $r + 1;
        $f->mergeCells("B{$zone}:C".($zone + 3));
        $f->mergeCells("E{$zone}:F".($zone + 3));
        self::cadre($f, "B{$zone}:C".($zone + 3));
        self::cadre($f, "E{$zone}:F".($zone + 3));

        return $zone + 3;
    }

    private static function miseEnPage(Worksheet $f, int $derniere): void
    {
        $f->getPageSetup()
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)->setFitToHeight(0)
            ->setHorizontalCentered(true)
            ->setPrintArea("A1:G{$derniere}");
        $f->getPageMargins()->setTop(0.4)->setBottom(0.75)->setLeft(0.4)->setRight(0.4)->setFooter(0.25);

        $s = config('societe');
        $legales = collect(['ICE' => $s['ice'], 'RC' => $s['rc'], 'IF' => $s['if'], 'Patente' => $s['patente'], 'CNSS' => $s['cnss']])
            ->filter()->map(fn ($v, $k) => "{$k} : {$v}")->implode(' · ');
        $siege = $s['adresse'] ? 'Siège social : '.$s['adresse'].($s['ville'] ? ', '.$s['ville'] : '') : '';
        $pied = collect([self::raisonSociale().($s['capital'] > 0 ? ' au capital de '.number_format($s['capital'], 2, ',', ' ').' DH' : ''), $siege, $legales])
            ->filter()->implode("\n");

        $f->getHeaderFooter()->setOddFooter('&C&"Segoe UI,Regular"&7&K64748B'.str_replace('&', '&&', $pied).'&R&"Segoe UI,Regular"&8&K1F4F86&P/&N');
        $f->setSelectedCell('A1');
    }

    /** Bandeau de titre de bloc : petites capitales blanches sur marine, filet or. */
    private static function bandeau(Worksheet $f, string $plage, string $texte): void
    {
        $f->mergeCells($plage);
        $premiere = explode(':', $plage)[0];
        $f->setCellValue($premiere, $texte);
        $style = $f->getStyle($plage);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::MARINE);
        $style->getFont()->setBold(true)->setSize(7.5)->getColor()->setRGB(self::OR_CLAIR);
        $style->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::OR);
        $style->getAlignment()->setIndent(1);
        $f->getRowDimension((int) preg_replace('/\D/', '', $premiere))->setRowHeight(18);
    }

    private static function cadre(Worksheet $f, string $plage, string $couleur = self::BORD_FORT): void
    {
        $f->getStyle($plage)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($couleur);
    }

    private static function filet(Worksheet $f, string $plage, string $couleur, string $style): void
    {
        $f->getStyle($plage)->getBorders()->getBottom()->setBorderStyle($style)->getColor()->setRGB($couleur);
    }

    /** « C:D » fusionne les deux colonnes sur la ligne et renvoie la première cellule. */
    private static function cellule(Worksheet $f, string $plage, int $ligne): string
    {
        [$de, $a] = array_pad(explode(':', $plage), 2, null);

        if ($a) {
            $f->mergeCells("{$de}{$ligne}:{$a}{$ligne}");
        }

        return "{$de}{$ligne}";
    }

    private static function raisonSociale(): string
    {
        return trim(config('societe.nom').' '.config('societe.forme'));
    }
}
