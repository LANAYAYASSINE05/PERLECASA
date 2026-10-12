<?php

namespace App\Filament\Resources;

use App\Enums\TypeCompte;
use App\Filament\Concerns\AccesParEcran;
use App\Filament\Resources\CompteResource\Pages;
use App\Models\Compte;
use App\Support\Montant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompteResource extends Resource
{
    use AccesParEcran;

    protected static string $ecran = 'comptes';

    protected static ?string $model = Compte::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Trésorerie';

    protected static ?string $modelLabel = 'compte';

    protected static ?string $navigationLabel = 'Comptes et caisses';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nom')->required()->maxLength(255)->placeholder('Banque Populaire – compte courant'),
            Forms\Components\ToggleButtons::make('type')->options(TypeCompte::class)->required()->inline()->live()
                ->default(TypeCompte::Banque->value),
            Forms\Components\TextInput::make('banque')->maxLength(255)
                ->visible(fn (Get $get) => self::estBanque($get('type'))),
            Forms\Components\TextInput::make('rib')->label('RIB')->maxLength(34)
                ->visible(fn (Get $get) => self::estBanque($get('type'))),
            Forms\Components\TextInput::make('adresse_agence')->label('Adresse de l’agence')->maxLength(255)
                ->helperText('Imprimée sur les factures et les reçus')
                ->visible(fn (Get $get) => self::estBanque($get('type'))),
            Forms\Components\TextInput::make('solde_initial')->label('Solde initial')->numeric()->required()->default(0)->suffix('MAD'),
            Forms\Components\Toggle::make('actif')->default(true)->inline(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withSum('encaissements', 'montant')->withSum('decaissements', 'montant'))
            ->defaultSort('type')
            ->columns([
                Tables\Columns\TextColumn::make('nom')->searchable()->weight('bold')
                    ->description(fn (Compte $c) => collect([$c->banque, $c->rib])->filter()->implode(' · ') ?: null),
                Tables\Columns\TextColumn::make('type')->visibleFrom('md')->badge(),
                Tables\Columns\TextColumn::make('solde_initial')->visibleFrom('lg')->label('Solde initial')->alignEnd()
                    ->formatStateUsing(fn ($state) => Montant::mad($state)),
                Tables\Columns\TextColumn::make('encaissements_sum_montant')->visibleFrom('lg')->label('Entrées')->alignEnd()->color('success')
                    ->formatStateUsing(fn ($state) => Montant::mad($state))->default(0),
                Tables\Columns\TextColumn::make('decaissements_sum_montant')->visibleFrom('lg')->label('Sorties')->alignEnd()->color('danger')
                    ->formatStateUsing(fn ($state) => Montant::mad($state))->default(0),
                Tables\Columns\TextColumn::make('solde')->label('Solde')->alignEnd()->weight('bold')
                    ->state(fn (Compte $c) => (float) $c->solde_initial + (float) $c->encaissements_sum_montant - (float) $c->decaissements_sum_montant)
                    ->formatStateUsing(fn ($state) => Montant::mad($state))
                    ->color(fn ($state) => $state < 0 ? 'danger' : null),
                Tables\Columns\IconColumn::make('actif')->visibleFrom('md')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Compte $c) => $c->encaissements_sum_montant !== null || $c->decaissements_sum_montant !== null),
            ]);
    }

    /** @return array<int, string> comptes actifs, libellés avec le solde disponible */
    public static function options(?TypeCompte $type = null): array
    {
        return Compte::query()
            ->where('actif', true)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('type')->orderBy('nom')
            ->get()
            ->mapWithKeys(fn (Compte $c) => [$c->id => "{$c->nom} — ".Montant::mad($c->solde())])
            ->all();
    }

    private static function estBanque(mixed $type): bool
    {
        return ($type instanceof TypeCompte ? $type : TypeCompte::tryFrom((string) $type)) === TypeCompte::Banque;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageComptes::route('/')];
    }
}
