<?php

namespace App\Filament\Widgets;

use App\Enums\TypeCompte;
use App\Filament\Resources\CompteResource;
use App\Models\Compte;
use App\Support\Montant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Soldes des comptes actifs et trésorerie totale, hors période des cartes du jour. */
class ComptesTresorerie extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected ?string $heading = 'Comptes et trésorerie';

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->accede('comptes') || $user?->accede('investissements'));
    }

    protected function getColumns(): int
    {
        return max(1, min(4, count($this->getCachedStats())));
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $comptes = Compte::where('actif', true)->orderBy('type')->orderBy('nom')->get();
        $total = $comptes->sum(fn (Compte $c) => $c->solde());
        $url = $user->accede('comptes') ? CompteResource::getUrl('index') : null;
        $banques = $comptes->where('type', TypeCompte::Banque)->count();
        $caisses = $comptes->where('type', TypeCompte::Caisse)->count();

        return [
            Stat::make('Trésorerie disponible', Montant::mad($total))
                ->description(collect([
                    $banques ? trans_choice('{1} :count banque|[2,*] :count banques', $banques) : null,
                    $caisses ? trans_choice('{1} :count caisse|[2,*] :count caisses', $caisses) : null,
                ])->filter()->implode(' · ') ?: 'Aucun compte actif')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('primary')
                ->url($url),
            ...$comptes->map(fn (Compte $c) => Stat::make($c->nom, Montant::mad($c->solde()))
                ->description($c->type->getLabel().($c->banque ? ' · '.$c->banque : ''))
                ->descriptionIcon($c->type === TypeCompte::Caisse ? 'heroicon-m-banknotes' : 'heroicon-m-building-library')
                ->color($c->solde() >= 0 ? 'success' : 'danger')
                ->url($url))->all(),
        ];
    }
}
