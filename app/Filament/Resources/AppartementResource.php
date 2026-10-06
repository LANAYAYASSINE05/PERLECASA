<?php

namespace App\Filament\Resources;

use App\Enums\StatutAppartement;
use App\Filament\Concerns\AccesParEcran;
use App\Filament\Resources\AppartementResource\Pages;
use App\Models\Appartement;
use App\Support\Montant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppartementResource extends Resource
{
    use AccesParEcran;

    protected static string $ecran = 'referentiel';

    protected static ?string $model = Appartement::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationGroup = 'Référentiel';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'appartement';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('programme_id')->label('Programme')
                ->relationship('programme', 'nom')->required()->preload()->searchable(),
            Forms\Components\TextInput::make('reference')->label('Référence')->required()->maxLength(20)->placeholder('A101'),
            Forms\Components\Select::make('typologie')->required()
                ->options(['Studio' => 'Studio', '2 pièces' => '2 pièces', '3 pièces' => '3 pièces', '4 pièces' => '4 pièces', 'Duplex' => 'Duplex', 'Local commercial' => 'Local commercial']),
            Forms\Components\TextInput::make('surface')->numeric()->required()->minValue(1)->suffix('m²'),
            Forms\Components\TextInput::make('prix')->label('Prix de vente')->numeric()->required()->minValue(0)->suffix('MAD'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['programme', 'vente.client']))
            ->defaultSort('reference')
            ->columns([
                Tables\Columns\TextColumn::make('programme.nom')->visibleFrom('md')->label('Programme')->sortable(),
                Tables\Columns\TextColumn::make('reference')->label('Réf.')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('typologie')->visibleFrom('md'),
                Tables\Columns\TextColumn::make('surface')->visibleFrom('lg')->suffix(' m²')->alignEnd(),
                Tables\Columns\TextColumn::make('prix')->formatStateUsing(fn ($state) => Montant::mad($state))->alignEnd()->sortable(),
                Tables\Columns\TextColumn::make('statut')->badge()
                    ->description(fn (Appartement $a) => $a->vente?->client?->nomComplet()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('programme')->relationship('programme', 'nom'),
                Tables\Filters\SelectFilter::make('statut')->options(StatutAppartement::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->hidden(fn (Appartement $a) => $a->statut === StatutAppartement::Vendu),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAppartements::route('/')];
    }
}
