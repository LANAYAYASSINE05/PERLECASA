<?php

namespace App\Filament\Widgets;

use App\Enums\TypeDecaissement;
use App\Filament\Resources\ChargeFixeResource;
use App\Filament\Resources\DecaissementResource;
use App\Filament\Resources\EncaissementResource;
use App\Filament\Resources\VenteResource;
use App\Models\ChargeFixe;
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

/** Mouvements de la période choisie (aujourd'hui par défaut) ; chaque carte suit les écrans du rôle, le caissier ne voit que la caisse. */
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

    /** @return array{encaisse: float, encaissements: int, decaisse: float, decaissements: int, ventes: float, nbVentes: int, caisse: float, nbCaisse: int, caisseNonJustifiee: int, chargesARegler: float, nbCharges: int, nbRetards: int} */
    public static function chiffres(User $user, ?PeriodeTableau $periode = null): array
    {
        $periode ??= PeriodeTableau::depuisFiltres(null);
        $entre = fn (Builder $q, string $colonne) => $q
            ->whereDate($colonne, '>=', $periode->debut)
            ->whereDate($colonne, '<=', $periode->fin);

        $encaissements = $entre(Encaissement::query(), 'date_operation');
        $decaissements = $entre(self::decaissements($user), 'date_operation');
        $caisse = (clone $decaissements)->where('type', TypeDecaissement::OperationCaisse);
        $ventes = $entre(Vente::query(), 'date_vente');
        $charges = $user->accede('charges')
            ? ChargeFixe::where('actif', true)->get()->filter(fn (ChargeFixe $c) => $c->prochainePeriode() <= now()->format('Y-m'))
            : collect();

        return [
            'encaisse' => (float) (clone $encaissements)->sum('montant'),
            'encaissements' => (clone $encaissements)->count(),
            'decaisse' => (float) (clone $decaissements)->sum('montant'),
            'decaissements' => (clone $decaissements)->count(),
            'ventes' => (float) (clone $ventes)->sum('prix_vente'),
            'nbVentes' => (clone $ventes)->count(),
            'caisse' => (float) (clone $caisse)->sum('montant'),
            'nbCaisse' => (clone $caisse)->count(),
            'caisseNonJustifiee' => (clone $caisse)->where('justifie', false)->count(),
            'chargesARegler' => (float) $charges->sum('montant'),
            'nbCharges' => $charges->count(),
            'nbRetards' => $charges->filter(fn (ChargeFixe $c) => $c->estEnRetard())->count(),
        ];
    }

    private static function decaissements(User $user): Builder
    {
        return $user->accede('decaissements') ? DecaissementResource::getEloquentQuery() : Decaissement::query();
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
                ->description($operations($c['decaissements']))
                ->descriptionIcon('heroicon-m-arrow-up-tray')->color('danger')
                ->url($user->accede('decaissements') ? DecaissementResource::getUrl('index') : null) : null,

            $voitEncaissements ? Stat::make($periode->unJour() ? 'Solde du jour' : 'Solde de la période', Montant::mad($solde))
                ->description('Encaissé moins décaissé')
                ->descriptionIcon($solde >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($solde >= 0 ? 'primary' : 'danger') : null,

            $user->accede('ventes') || $user->estAssocie() ? Stat::make("Ventes {$quand}", $c['nbVentes'])
                ->description($c['nbVentes'] ? Montant::mad($c['ventes']).' signés' : 'Aucune vente signée')
                ->descriptionIcon('heroicon-m-key')->color($c['nbVentes'] ? 'success' : 'gray')
                ->url($user->accede('ventes') ? VenteResource::getUrl('index') : null) : null,

            $user->accede('decaissements') ? Stat::make("Caisse {$quand}", Montant::mad($c['caisse']))
                ->description($c['caisseNonJustifiee']
                    ? "{$c['nbCaisse']} opération(s), dont {$c['caisseNonJustifiee']} sans pièce"
                    : $operations($c['nbCaisse']))
                ->descriptionIcon($c['caisseNonJustifiee'] ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-banknotes')
                ->color($c['caisseNonJustifiee'] ? 'danger' : 'gray')
                ->url(DecaissementResource::getUrl('index', $c['caisseNonJustifiee'] ? ['activeTab' => 'non_justifiees'] : [])) : null,

            $user->accede('charges') ? Stat::make('Charges fixes à régler', Montant::mad($c['chargesARegler']))
                ->description(match (true) {
                    $c['nbRetards'] > 0 => "{$c['nbCharges']} charge(s), dont {$c['nbRetards']} en retard",
                    $c['nbCharges'] > 0 => "{$c['nbCharges']} charge(s) ce mois",
                    default => 'Tout est réglé',
                })
                ->descriptionIcon($c['nbRetards'] ? 'heroicon-m-clock' : 'heroicon-m-check-circle')
                ->color($c['nbRetards'] ? 'danger' : ($c['nbCharges'] ? 'warning' : 'success'))
                ->url(ChargeFixeResource::getUrl('index')) : null,
        ]));
    }
}
