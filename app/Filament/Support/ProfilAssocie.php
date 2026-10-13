<?php

namespace App\Filament\Support;

use App\Services\Alertes;
use Closure;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;

/** Coordonnées et RIB de l'associé, utilisés par l'administrateur pour ses remboursements. */
class ProfilAssocie
{
    private const CHAMPS = ['telephone', 'cin', 'adresse', 'banque', 'rib'];

    public static function action(): Action
    {
        return Action::make('profil')->label('Mon profil')
            ->icon('heroicon-o-identification')->color('gray')
            ->modalHeading('Mon profil')
            ->modalDescription('Le nom et l’e-mail sont gérés par l’administrateur. Il est prévenu de tout changement de RIB.')
            ->modalSubmitActionLabel('Enregistrer')
            ->fillForm(fn () => auth()->user()->only(self::CHAMPS))
            ->form([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('telephone')->label('Téléphone')->tel()->maxLength(30),
                    Forms\Components\TextInput::make('cin')->label('CIN')->maxLength(20),
                    Forms\Components\TextInput::make('adresse')->maxLength(255)->columnSpanFull(),
                    Forms\Components\TextInput::make('banque')->maxLength(100),
                    Forms\Components\TextInput::make('rib')->label('RIB (24 chiffres)')
                        ->requiredWith('banque')
                        ->dehydrateStateUsing(fn (?string $state) => self::rib($state))
                        ->rule(fn () => function (string $attribut, $valeur, Closure $echec): void {
                            if (filled($valeur) && ! preg_match('/^\d{24}$/', (string) self::rib($valeur))) {
                                $echec('Le RIB doit compter exactement 24 chiffres.');
                            }
                        }),
                ]),
            ])
            ->action(fn (array $data) => self::enregistrer($data));
    }

    private static function enregistrer(array $data): void
    {
        $user = auth()->user();
        $user->forceFill(collect($data)->only(self::CHAMPS)->map(fn ($v) => filled($v) ? $v : null)->all());

        if (! $user->isDirty()) {
            Notification::make()->info()->title('Aucune modification')->send();

            return;
        }

        $ribModifie = $user->isDirty(['rib', 'banque']);
        $user->save();
        Alertes::coordonneesAssocie($user, $ribModifie);

        Notification::make()->success()->title('Profil enregistré')->send();
    }

    private static function rib(?string $valeur): ?string
    {
        $chiffres = preg_replace('/\s+/', '', (string) $valeur);

        return $chiffres === '' ? null : $chiffres;
    }
}
