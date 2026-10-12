<?php

namespace App\Filament\Pages;

use App\Filament\Support\PageAssocie;
use App\Services\Alertes;
use Closure;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;

/** Coordonnées et RIB de l'associé, utilisés par l'administrateur pour ses remboursements. */
class ProfilAssocie extends PageAssocie implements HasForms
{
    use InteractsWithForms;

    private const CHAMPS = ['telephone', 'cin', 'adresse', 'banque', 'rib'];

    protected static string $view = 'filament.pages.profil-associe';

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Mon investissement';

    protected static ?string $title = 'Mon profil';

    protected static ?string $slug = 'espace-associe/profil';

    protected static ?int $navigationSort = 4;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(auth()->user()->only(['name', 'email', ...self::CHAMPS]));
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Section::make('Identité')
                ->description('Le nom et l’e-mail sont gérés par l’administrateur.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Nom')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('email')->label('E-mail')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('telephone')->label('Téléphone')->tel()->maxLength(30),
                    Forms\Components\TextInput::make('cin')->label('CIN')->maxLength(20),
                    Forms\Components\TextInput::make('adresse')->maxLength(255)->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Coordonnées bancaires')
                ->description('Compte sur lequel vos remboursements sont virés. L’administrateur est prévenu de tout changement.')
                ->columns(2)
                ->schema([
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
        ]);
    }

    public function enregistrer(): void
    {
        $user = auth()->user();
        $user->forceFill(collect($this->form->getState())->only(self::CHAMPS)->map(fn ($v) => filled($v) ? $v : null)->all());

        if (! $user->isDirty()) {
            Notification::make()->info()->title('Aucune modification')->send();

            return;
        }

        $ribModifie = $user->isDirty(['rib', 'banque']);
        $user->save();
        Alertes::coordonneesAssocie($user, $ribModifie);

        $this->form->fill($user->only(['name', 'email', ...self::CHAMPS]));
        Notification::make()->success()->title('Profil enregistré')->send();
    }

    private static function rib(?string $valeur): ?string
    {
        $chiffres = preg_replace('/\s+/', '', (string) $valeur);

        return $chiffres === '' ? null : $chiffres;
    }
}
