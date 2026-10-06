<?php

use App\Enums\Role;

it('affiche l’écran de chargement sur la connexion et dans l’application', function () {
    $this->get('/login')->assertOk()->assertSee('id="pc-chargement"', false);

    $this->actingAs(utilisateur(Role::Administrateur))->get('/ventes')
        ->assertSee('id="pc-chargement"', false)
        ->assertSee('id="pc-chargement-mini"', false);
});

it('télécharge les pièces Excel sans déclencher l’écran de chargement', function () {
    vente();

    $html = $this->actingAs(utilisateur(Role::Administrateur))->get('/ventes')->getContent();

    expect($html)->toMatch('/<a\b(?=[^>]*\bdownload\b)[^>]*href="[^"]*\/facture"/');
});
