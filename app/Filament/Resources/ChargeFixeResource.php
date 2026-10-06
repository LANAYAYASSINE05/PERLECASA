<?php

namespace App\Filament\Resources;

use App\Enums\CategorieCharge;
use App\Enums\ModePaiement;
use App\Enums\Periodicite;
use App\Enums\TypeCompte;
use App\Enums\TypeDecaissement;
use App\Filament\Concerns\AccesParEcran;
use App\Filament\Resources\ChargeFixeResource\Pages;
use App\Filament\Support\Enregistrement;
use App\Filament\Support\RegleFinance;
use App\Models\ChargeFixe;
use App\Models\Compte;
use App\Models\Decaissement;
use App\Support\Montant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ChargeFixeResource extends Resource
{
    use AccesParEcran;

    protected static string $ecran = 'charges';

    protected static ?string $model = ChargeFixe::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Décaissements';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'charge fixe';

    protected static ?string $pluralModelLabel = 'charges fixes';

    protected static ?string $slug = 'charges-fixes';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('libelle')->label('Libellé')->required()->maxLength(255)->placeholder('Loyer du siège')
                ->columnSpanFull(),
            Forms\Components\Select::make('categorie')->label('Catégorie')->options(CategorieCharge::class)->required(),
            Forms\Components\Select::make('periodicite')->label('Périodicité')->options(Periodicite::class)->required()
                ->default(Periodicite::Mensuelle->value),
            Forms\Components\TextInput::make('montant')->label('Montant habituel')->numeric()->required()->minValue(0.01)->suffix('MAD'),
            Forms\Components\Select::make('fournisseur_id')->label('Fournisseur / créancier')
                ->relationship('fournisseur', 'raison_sociale')->searchable()->preload()
                ->createOptionForm(FournisseurResource::champs()),
            Forms\Components\Toggle::make('actif')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('fournisseur')->withMax('decaissements', 'periode'))
            ->defaultSort('libelle')
            ->columns([
                Tables\Columns\TextColumn::make('libelle')->label('Libellé')->searchable()->sortable()->weight('bold')
                    ->description(fn (ChargeFixe $c) => $c->fournisseur?->raison_sociale),
                Tables\Columns\TextColumn::make('categorie')->visibleFrom('lg')->label('Catégorie')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('periodicite')->visibleFrom('md')->label('Périodicité'),
                Tables\Columns\TextColumn::make('montant')->alignEnd()->sortable()
                    ->formatStateUsing(fn ($state) => Montant::mad($state)),
                Tables\Columns\TextColumn::make('decaissements_max_periode')->visibleFrom('lg')->label('Dernière période payée')->placeholder('Jamais'),
                Tables\Columns\TextColumn::make('prochaine')->label('À payer')
                    ->state(fn (ChargeFixe $c) => $c->actif ? $c->prochainePeriode() : null)
                    ->badge()
                    ->color(fn (ChargeFixe $c) => $c->estEnRetard() ? 'danger' : 'info')
                    ->description(fn (ChargeFixe $c) => $c->estEnRetard() ? 'En retard' : null),
                Tables\Columns\ToggleColumn::make('actif')->visibleFrom('md'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categorie')->label('Catégorie')->options(CategorieCharge::class),
                Tables\Filters\TernaryFilter::make('actif')->default(true),
            ])
            ->actions([
                Tables\Actions\Action::make('payer')->label('Payer')
                    ->icon('heroicon-o-banknotes')->color('success')
                    ->visible(fn (ChargeFixe $c) => $c->actif && auth()->user()->accede('decaissements'))
                    ->modalHeading(fn (ChargeFixe $c) => "Payer — {$c->libelle}")
                    ->fillForm(fn (ChargeFixe $c) => [
                        'periode' => $c->prochainePeriode(),
                        'montant' => $c->montant,
                        'date_operation' => now()->toDateString(),
                        'mode' => ModePaiement::Virement->value,
                        'compte_id' => Compte::where('actif', true)->where('type', TypeCompte::Banque)->value('id'),
                    ])
                    ->form([
                        Forms\Components\TextInput::make('periode')->label('Période réglée')->required()->mask('9999-99')
                            ->rule(RegleFinance::decaissement('periode', self::donneesPaiement(...))),
                        Forms\Components\DatePicker::make('date_operation')->label('Date')->required()->maxDate(now()),
                        Forms\Components\Select::make('compte_id')->label('Compte débité')->required()
                            ->options(fn () => CompteResource::options()),
                        Forms\Components\Select::make('mode')->label('Mode')->options(ModePaiement::class)->required(),
                        Forms\Components\TextInput::make('montant')->numeric()->required()->suffix('MAD')
                            ->rule(RegleFinance::decaissement('montant', self::donneesPaiement(...))),
                        DecaissementResource::champPiece(),
                    ])
                    ->action(function (ChargeFixe $record, array $data) {
                        $decaissement = Enregistrement::proteger(fn () => Decaissement::create([
                            ...$data,
                            'type' => TypeDecaissement::ChargeFixe,
                            'charge_fixe_id' => $record->id,
                            'motif' => $record->libelle,
                        ]));

                        Notification::make()->success()
                            ->title("{$decaissement->numero} : {$record->libelle} {$decaissement->periode} payée")
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->hidden(fn (ChargeFixe $c) => $c->decaissements_max_periode !== null),
            ]);
    }

    private static function donneesPaiement(Get $get, ChargeFixe $record): array
    {
        return [[
            'type' => TypeDecaissement::ChargeFixe->value,
            'charge_fixe_id' => $record->id,
            'periode' => $get('periode'),
            'compte_id' => $get('compte_id'),
            'montant' => $get('montant'),
        ], null];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageChargeFixes::route('/')];
    }
}
