<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DecaissementResource;
use App\Models\Compte;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Models\Vente;
use App\Support\Montant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ChiffresFinance extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('encaissements');
    }

    protected function getStats(): array
    {
        $debut = now()->startOfMonth();
        $encaisse = (float) Encaissement::where('date_operation', '>=', $debut)->sum('montant');
        $decaisse = (float) Decaissement::where('date_operation', '>=', $debut)->sum('montant');
        $tresorerie = Compte::where('actif', true)->get()->sum(fn (Compte $c) => $c->solde());

        $resteDu = (float) Vente::query()
            ->selectRaw('coalesce(sum(prix_vente - (select coalesce(sum(montant), 0) from encaissements where encaissements.vente_id = ventes.id)), 0) as reste')
            ->value('reste');

        $nonJustifiees = Decaissement::caisseNonJustifiee()->select(DB::raw('count(*) as nombre'), DB::raw('coalesce(sum(montant), 0) as total'))->first();

        return [
            Stat::make('Encaissé ce mois', Montant::mad($encaisse))
                ->description('Ventes d’appartements et virements reçus')
                ->descriptionIcon('heroicon-m-arrow-down-tray')->color('success'),
            Stat::make('Décaissé ce mois', Montant::mad($decaisse))
                ->description('Charges fixes, fournisseurs, caisse')
                ->descriptionIcon('heroicon-m-arrow-up-tray')->color('danger'),
            Stat::make('Trésorerie disponible', Montant::mad($tresorerie))
                ->description('Banques et caisses')
                ->descriptionIcon('heroicon-m-building-library')->color('primary'),
            Stat::make('Reste à encaisser', Montant::mad($resteDu))
                ->description('Sur les appartements vendus')
                ->descriptionIcon('heroicon-m-key')->color('warning'),
            Stat::make('Caisse non justifiée', Montant::mad($nonJustifiees->total))
                ->description($nonJustifiees->nombre.' opération(s) sans pièce')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($nonJustifiees->nombre ? 'danger' : 'success')
                ->url(DecaissementResource::getUrl('index', ['activeTab' => 'non_justifiees'])),
        ];
    }
}
