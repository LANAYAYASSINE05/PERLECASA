<?php

use App\Enums\Role;
use App\Enums\TypeCompte;
use App\Models\Appartement;
use App\Models\Client;
use App\Models\Compte;
use App\Models\Programme;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function utilisateur(Role $role = Role::Comptable): User
{
    return User::factory()->role($role)->create();
}

function compte(TypeCompte $type = TypeCompte::Banque, float $soldeInitial = 100000): Compte
{
    return Compte::create(['nom' => $type->getLabel().' '.uniqid(), 'type' => $type, 'solde_initial' => $soldeInitial]);
}

function appartement(float $prix = 1000000): Appartement
{
    $programme = Programme::firstOrCreate(['nom' => 'Programme test'], ['ville' => 'Casablanca']);

    return Appartement::create([
        'programme_id' => $programme->id, 'reference' => 'T'.uniqid(), 'typologie' => '3 pièces', 'surface' => 90, 'prix' => $prix,
    ]);
}

function vente(float $prix = 1000000): Vente
{
    $client = Client::create(['nom' => 'Client', 'prenom' => 'Test']);

    return Vente::create([
        'appartement_id' => appartement($prix)->id, 'client_id' => $client->id, 'date_vente' => now(), 'prix_vente' => $prix,
    ]);
}
