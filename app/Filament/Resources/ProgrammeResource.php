<?php

namespace App\Filament\Resources;

use App\Enums\StatutAppartement;
use App\Filament\Concerns\AccesParEcran;
use App\Filament\Resources\ProgrammeResource\Pages;
use App\Models\Programme;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProgrammeResource extends Resource
{
    use AccesParEcran;

    protected static string $ecran = 'referentiel';

    protected static ?string $model = Programme::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Référentiel';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'programme';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nom')->required()->maxLength(255),
            Forms\Components\TextInput::make('ville')->required()->default('Casablanca')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->withCount('appartements')
                ->withCount(['appartements as vendus_count' => fn ($q) => $q->where('statut', StatutAppartement::Vendu)]))
            ->columns([
                Tables\Columns\TextColumn::make('nom')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('ville')->visibleFrom('md')->searchable(),
                Tables\Columns\TextColumn::make('appartements_count')->label('Appartements')->alignEnd(),
                Tables\Columns\TextColumn::make('vendus_count')->label('Vendus')->alignEnd()
                    ->description(fn (Programme $p) => $p->appartements_count ? round($p->vendus_count * 100 / $p->appartements_count).' %' : null),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->hidden(fn (Programme $p) => $p->appartements_count > 0),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageProgrammes::route('/')];
    }
}
