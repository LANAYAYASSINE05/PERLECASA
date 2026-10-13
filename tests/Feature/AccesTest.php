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
    'comptable : associés interdits' => [Role::Comptable, '/associes', 403],
    'comptable : mes investissements interdits' => [Role::Comptable, '/mes-investissements', 403],
    'associé : tableau de bord' => [Role::Associe, '/', 200],
    'associé : mes investissements' => [Role::Associe, '/mes-investissements', 200],
    'associé : résultat' => [Role::Associe, '/espace-associe/resultat', 200],
    'associé : trésorerie' => [Role::Associe, '/espace-associe/tresorerie', 200],
    'associé : programmes' => [Role::Associe, '/espace-associe/programmes', 200],
    'comptable : espace associé interdit' => [Role::Comptable, '/espace-associe/tresorerie', 403],
    'associé : encaissements interdits' => [Role::Associe, '/encaissements', 403],
    'associé : ventes interdites' => [Role::Associe, '/ventes', 403],
    'associé : décaissements interdits' => [Role::Associe, '/decaissements', 403],
    'associé : comptes interdits' => [Role::Associe, '/comptes', 403],
    'associé : clients interdits' => [Role::Associe, '/clients', 403],
    'associé : utilisateurs interdits' => [Role::Associe, '/utilisateurs', 403],
    'associé : récapitulatif des associés interdit' => [Role::Associe, '/associes', 403],
    'administrateur : utilisateurs' => [Role::Administrateur, '/utilisateurs', 200],
    'administrateur : associés' => [Role::Administrateur, '/associes', 200],
    'administrateur : mes investissements interdits' => [Role::Administrateur, '/mes-investissements', 403],
]);

it('refuse l’accès à un compte désactivé', function () {
    $utilisateur = utilisateur();
    $utilisateur->update(['actif' => false]);

    $this->actingAs($utilisateur)->get('/')->assertForbidden();
});
