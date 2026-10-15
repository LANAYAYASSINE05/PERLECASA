<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DecaissementResource;
use App\Filament\Resources\EncaissementResource;
use App\Filament\Resources\VenteResource;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Models\User;
use App\Models\Vente;
use App\Support\Montant;
use App\Support\PeriodeTableau;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Session;

/**
 * Les quatre chiffres clés du tableau de bord, sur la période choisie dans les onglets.
 * Trésorerie et caisse à justifier sont dans le bandeau ; charges et caisse ont leur tableau plus bas.
 */
class ChiffresDuJour extends StatsOverviewWidget
{
    protected static string $view = 'filament.widgets.chiffres-du-jour';

    #[Session]
    public string $periode = 'aujourdhui';

    #[Session]
    public ?string $date = null;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('tableau');
    }

    public function updatedPeriode(string $valeur): void
    {
        if ($valeur === 'date' && blank($this->date)) {
            $this->date = today()->toDateString();
        }
    }

    private function periode(): PeriodeTableau
    {
        return PeriodeTableau::depuisFiltres(['periode' => $this->periode, 'date' => $this->date]);
    }

    protected function getHeading(): ?string
    {
        return $this->periode()->titre();
    }

    protected function getColumns(): int
    {
        return max(1, min(4, count($this->getCachedStats())));
    }

    /** @return array{encaisse: float, encaissements: int, decaisse: float, decaissements: int, sansPiece: int, resteAEncaisser: float} */
    public static function chiffres(User $user, ?PeriodeTableau $periode = null): array
    {
        $periode ??= PeriodeTableau::depuisFiltres(null);
        $entre = fn (Builder $q) => $q
            ->whereDate('date_operation', '>=', $periode->debut)
            ->whereDate('date_operation', '<=', $periode->fin);

        $encaissements = $entre(Encaissement::query());
        $decaissements = $entre($user->accede('decaissements') ? DecaissementResource::getEloquentQuery() : Decaissement::query());

        return [
            'encaisse' => (float) (clone $encaissements)->sum('montant'),
            'encaissements' => (clone $encaissements)->count(),
            'decaisse' => (float) (clone $decaissements)->sum('montant'),
            'decaissements' => (clone $decaissements)->count(),
            'sansPiece' => (clone $decaissements)->caisseNonJustifiee()->count(),
            'resteAEncaisser' => (float) Vente::query()
                ->selectRaw('coalesce(sum(prix_vente - (select coalesce(sum(montant), 0) from encaissements where encaissements.vente_id = ventes.id)), 0) as reste')
                ->value('reste'),
        ];
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $periode = $this->periode();
        $quand = $periode->suffixe();
        $c = self::chiffres($user, $periode);
        $voitEncaissements = $user->accede('encaissements') || $user->estAssocie();
        $voitDecaissements = $user->accede('decaissements') || $user->estAssocie();
        $solde = $c['encaisse'] - $c['decaisse'];
        $operations = fn (int $n) => trans_choice('{0} aucune opération|{1} 1 opération|[2,*] :count opérations', $n);

        return array_values(array_filter([
            $voitEncaissements ? Stat::make("Encaissé {$quand}", Montant::mad($c['encaisse']))
                ->description($operations($c['encaissements']))
                ->descriptionIcon('heroicon-m-arrow-down-tray')->color('success')
                ->url($user->accede('encaissements') ? EncaissementResource::getUrl('index') : null) : null,

            $voitDecaissements ? Stat::make(($user->estCaissier() ? 'Sorti de caisse ' : 'Décaissé ').$quand, Montant::mad($c['decaisse']))
                ->description($c['sansPiece'] && $user->accede('decaissements')
                    ? $operations($c['decaissements']).", dont {$c['sansPiece']} sans pièce"
                    : $operations($c['decaissements']))
                ->descriptionIcon($c['sansPiece'] && $user->accede('decaissements') ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-arrow-up-tray')
                ->color('danger')
                ->url($user->accede('decaissements') ? DecaissementResource::getUrl('index') : null) : null,

            $voitEncaissements ? Stat::make($periode->unJour() ? 'Solde du jour' : 'Solde de la période', Montant::mad($solde))
                ->description('Encaissé moins décaissé')
                ->descriptionIcon($solde >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($solde >= 0 ? 'primary' : 'danger') : null,

            $user->accede('ventes') || $user->estAssocie() ? Stat::make('Reste à encaisser', Montant::mad($c['resteAEncaisser']))
                ->description('Sur toutes les ventes signées')
                ->descriptionIcon('heroicon-m-key')->color('warning')
                ->url($user->accede('ventes') ? VenteResource::getUrl('index') : null) : null,
        ]));
    }
}
