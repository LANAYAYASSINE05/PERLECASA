<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AccesParEcran;
use App\Filament\Resources\FournisseurResource\Pages;
use App\Filament\Support\Avatar;
use App\Models\Fournisseur;
use App\Support\Montant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FournisseurResource extends Resource
{
    use AccesParEcran;

    protected static string $ecran = 'fournisseurs';

    protected static ?string $model = Fournisseur::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Décaissements';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'fournisseur';

    protected static ?string $recordTitleAttribute = 'raison_sociale';

    /** Champs réutilisés par la création rapide depuis un décaissement. */
    public static function champs(): array
    {
        return [
            Forms\Components\TextInput::make('raison_sociale')->label('Raison sociale')->required()->maxLength(255),
            Forms\Components\TextInput::make('ice')->label('ICE')->maxLength(20),
            Forms\Components\TextInput::make('telephone')->label('Téléphone')->tel()->maxLength(30),
            Forms\Components\TextInput::make('email')->label('E-mail')->email()->maxLength(255),
            Forms\Components\TextInput::make('adresse')->label('Adresse')->maxLength(255)->columnSpanFull(),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::champs())->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withSum('decaissements', 'montant')->withCount(['decaissements', 'chargesFixes']))
            ->defaultSort('raison_sociale')
            ->columns([
                Tables\Columns\TextColumn::make('raison_sociale')->label('Raison sociale')->searchable()->sortable()
                    ->formatStateUsing(fn (Fournisseur $record) => Avatar::html($record->raison_sociale, $record->code())),
                Tables\Columns\TextColumn::make('ice')->visibleFrom('lg')->label('ICE')->searchable(),
                Tables\Columns\TextColumn::make('telephone')->visibleFrom('md')->label('Téléphone'),
                Tables\Columns\TextColumn::make('decaissements_count')->visibleFrom('md')->label('Paiements')->alignEnd(),
                Tables\Columns\TextColumn::make('decaissements_sum_montant')->label('Total payé')->alignEnd()->sortable()
                    ->formatStateUsing(fn ($state) => Montant::mad($state))->default(0),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Fournisseur $f) => $f->decaissements_count + $f->charges_fixes_count > 0),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageFournisseurs::route('/')];
    }
}
