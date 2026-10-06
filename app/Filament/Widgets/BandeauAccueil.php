<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ChargeFixeResource;
use App\Filament\Resources\DecaissementResource;
use App\Filament\Resources\EncaissementResource;
use App\Filament\Resources\VenteResource;
use App\Models\Compte;
use App\Support\Montant;
use Filament\Widgets\Widget;

class BandeauAccueil extends Widget
{
    protected static string $view = 'filament.widgets.bandeau-accueil';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getViewData(): array
    {
        $user = auth()->user();
        $jour = now()->locale('fr');

        $actions = collect([
            ['ecran' => 'encaissements', 'libelle' => 'Nouvel encaissement', 'icone' => 'heroicon-o-arrow-down-tray', 'url' => EncaissementResource::getUrl('index', ['action' => 'create'])],
            ['ecran' => 'decaissements', 'libelle' => $user->estCaissier() ? 'Nouvelle opération de caisse' : 'Nouveau décaissement', 'icone' => 'heroicon-o-arrow-up-tray', 'url' => DecaissementResource::getUrl('index', ['action' => 'create'])],
            ['ecran' => 'ventes', 'libelle' => 'Appartements vendus', 'icone' => 'heroicon-o-key', 'url' => VenteResource::getUrl('index')],
            ['ecran' => 'charges', 'libelle' => 'Charges à payer', 'icone' => 'heroicon-o-calendar-days', 'url' => ChargeFixeResource::getUrl('index')],
        ])->filter(fn (array $a) => $user->accede($a['ecran']))->values()->all();

        $nonJustifiees = DecaissementResource::getEloquentQuery()->caisseNonJustifiee();

        return [
            'salutation' => now()->hour < 18 ? 'Bonjour' : 'Bonsoir',
            'prenom' => str($user->name)->before(' ')->toString() ?: $user->name,
            'agence' => 'Perle Casa Immobilier',
            'actions' => $actions,
            'date' => ucfirst($jour->isoFormat('dddd D MMMM YYYY')),
            'role' => $user->role->getLabel(),
            'tresorerie' => $user->accede('comptes')
                ? Montant::mad(Compte::where('actif', true)->get()->sum(fn (Compte $c) => $c->solde()))
                : '—',
            'nonJustifiees' => $nonJustifiees->count().' · '.Montant::mad($nonJustifiees->sum('montant')),
        ];
    }
}
