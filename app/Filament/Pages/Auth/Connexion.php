<?php

namespace App\Filament\Pages\Auth;

use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login;

/** Connexion : seuls les comptes actifs et non archivés peuvent s'authentifier. */
class Connexion extends Login
{
    protected static string $view = 'filament.pages.auth.connexion';

    protected static string $layout = 'layouts.connexion';

    public function mount(): void
    {
        parent::mount();

        if ($motif = session('motif_deconnexion')) {
            Notification::make()->warning()->title($motif)->send();
        }
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'email' => $data['email'],
            'password' => $data['password'],
            'actif' => true,
        ];
    }
}
