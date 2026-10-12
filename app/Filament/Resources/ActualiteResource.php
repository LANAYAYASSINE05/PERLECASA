<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AccesParEcran;
use App\Filament\Resources\ActualiteResource\Pages;
use App\Models\Actualite;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActualiteResource extends Resource
{
    use AccesParEcran;

    protected static string $ecran = 'associes';

    protected static ?string $model = Actualite::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $modelLabel = 'actualité';

    protected static ?string $pluralModelLabel = 'actualités des associés';

    protected static ?string $navigationLabel = 'Actualités associés';

    protected static ?string $slug = 'actualites';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('titre')->required()->maxLength(255)
                ->placeholder('Avancement du chantier, convocation à l’AG…'),
            Forms\Components\Textarea::make('contenu')->required()->rows(8)->maxLength(5000),
            Forms\Components\Toggle::make('epingle')->label('Épingler en haut de la page'),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\IconColumn::make('epingle')->label('')->icon(fn ($state) => $state ? 'heroicon-s-bookmark' : null)->color('warning'),
                Tables\Columns\TextColumn::make('titre')->searchable()->weight('bold')->wrap()
                    ->description(fn (Actualite $a) => str($a->contenu)->limit(100)),
                Tables\Columns\TextColumn::make('created_at')->label('Publiée le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageActualites::route('/')];
    }
}
