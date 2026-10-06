<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AccesParEcran;
use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Support\Avatar;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClientResource extends Resource
{
    use AccesParEcran;

    protected static string $ecran = 'referentiel';

    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Référentiel';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'client';

    /** Champs réutilisés par la création rapide depuis une vente. */
    public static function champs(): array
    {
        return [
            Forms\Components\TextInput::make('nom')->required()->maxLength(255),
            Forms\Components\TextInput::make('prenom')->label('Prénom')->maxLength(255),
            Forms\Components\TextInput::make('cin')->label('CIN')->maxLength(20),
            Forms\Components\TextInput::make('ice')->label('ICE')->maxLength(20)->helperText('Pour un client société'),
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
            ->modifyQueryUsing(fn ($query) => $query->withCount('ventes'))
            ->defaultSort('nom')
            ->columns([
                Tables\Columns\TextColumn::make('nom')->searchable(['nom', 'prenom'])->sortable()
                    ->formatStateUsing(fn (Client $record) => Avatar::html($record->nomComplet(), $record->email)),
                Tables\Columns\TextColumn::make('cin')->visibleFrom('md')->label('CIN')->searchable(),
                Tables\Columns\TextColumn::make('telephone')->label('Téléphone'),
                Tables\Columns\TextColumn::make('email')->visibleFrom('lg')->label('E-mail'),
                Tables\Columns\TextColumn::make('ventes_count')->visibleFrom('md')->label('Achats')->alignEnd(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->hidden(fn (Client $c) => $c->ventes_count > 0),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageClients::route('/')];
    }
}
