<?php

use App\Enums\Role;

it('redirige un visiteur vers la connexion', function () {
    $this->get('/encaissements')->assertRedirect('/login');
});

it('ouvre les écrans selon le rôle', function (Role $role, string $url, int $statut) {
    $this->actingAs(utilisateur($role))->get($url)->assertStatus($statut);
})->with([
    'comptable : tableau de bord' => [Role::Comptable, '/', 200],
    'comptable : ventes' => [Role::Comptable, '/ventes', 200],
    'comptable : encaissements' => [Role::Comptable, '/encaissements', 200],
    'comptable : décaissements' => [Role::Comptable, '/decaissements', 200],
    'comptable : charges fixes' => [Role::Comptable, '/charges-fixes', 200],
    'comptable : comptes' => [Role::Comptable, '/comptes', 200],
    'comptable : bons de commande' => [Role::Comptable, '/bons-commande', 200],
    'comptable : utilisateurs interdits' => [Role::Comptable, '/utilisateurs', 403],
    'caissier : tableau de bord' => [Role::Caissier, '/', 200],
    'caissier : décaissements' => [Role::Caissier, '/decaissements', 200],
    'caissier : encaissements interdits' => [Role::Caissier, '/encaissements', 403],
    'caissier : charges fixes interdites' => [Role::Caissier, '/charges-fixes', 403],
    'caissier : comptes interdits' => [Role::Caissier, '/comptes', 403],
    'agent d’encaissement : tableau de bord' => [Role::Encaisseur, '/', 200],
    'agent d’encaissement : encaissements' => [Role::Encaisseur, '/encaissements', 200],
    'agent d’encaissement : ventes interdites' => [Role::Encaisseur, '/ventes', 403],
    'agent d’encaissement : décaissements interdits' => [Role::Encaisseur, '/decaissements', 403],
    'agent d’encaissement : comptes interdits' => [Role::Encaisseur, '/comptes', 403],
    'agent d’encaissement : clients interdits' => [Role::Encaisseur, '/clients', 403],
    'agent d’encaissement : utilisateurs interdits' => [Role::Encaisseur, '/utilisateurs', 403],
    'administrateur : utilisateurs' => [Role::Administrateur, '/utilisateurs', 200],
]);

it('refuse l’accès à un compte désactivé', function () {
    $utilisateur = utilisateur();
    $utilisateur->update(['actif' => false]);

    $this->actingAs($utilisateur)->get('/')->assertForbidden();
});
