<?php

namespace App\Policies;

/** Gestion des documents réservée à l'administrateur ; les associés les consultent via leur espace. */
class DocumentAssociePolicy extends PolitiqueParEcran
{
    protected string $ecran = 'associes';
}
