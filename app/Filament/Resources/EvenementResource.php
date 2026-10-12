<?php

namespace App\Filament\Resources;

use App\Enums\TypeEvenement;
use App\Filament\Concerns\AccesParEcran;
use App\Filament\Resources\EvenementResource\Pages;
use App\Models\Evenement;
use App\Support\Montant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EvenementResource extends Resource
{
    use AccesParEcran;

    protected static string $ecran = 'associes';

    protected static ?string $model = Evenement::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $modelLabel = 'événement';

    protected static ?string $pluralModelLabel = 'agenda des associés';

    protected static ?string $navigationLabel = 'Agenda associés';

    protected static ?string $slug = 'agenda';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('titre')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\Select::make('type')->options(TypeEvenement::class)->required()->live()
                ->default(TypeEvenement::AssembleeGenerale->value),
            Forms\Components\DateTimePicker::make('date_debut')->label('Date et heure')->required()->seconds(false),
            Forms\Components\TextInput::make('lieu')->maxLength(255),
            Forms\Components\TextInput::make('montant')->label('Montant attendu')->numeric()->minValue(0)->suffix('MAD')
                ->visible(fn (Get $get) => $get('type') === TypeEvenement::Encaissement->value),
            Forms\Components\Textarea::make('description')->rows(4)->maxLength(2000)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_debut', 'desc')
            ->columns(self::colonnes())
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(TypeEvenement::class),
                Tables\Filters\Filter::make('a_venir')->label('À venir')->query(fn ($q) => $q->aVenir()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    /** Colonnes communes à la gestion (administrateur) et à la consultation (associé). */
    public static function colonnes(): array
    {
        return [
            Tables\Columns\TextColumn::make('date_debut')->label('Date')->dateTime('d/m/Y H:i')->sortable()->weight('bold')
                ->description(fn (Evenement $e) => ucfirst($e->date_debut->translatedFormat('l')).' · '.$e->date_debut->diffForHumans()),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\TextColumn::make('titre')->searchable()->wrap()
                ->description(fn (Evenement $e) => $e->description ? str($e->description)->limit(100) : null),
            Tables\Columns\TextColumn::make('lieu')->placeholder('—')->visibleFrom('md'),
            Tables\Columns\TextColumn::make('montant')->alignEnd()->placeholder('—')->visibleFrom('lg')
                ->formatStateUsing(fn ($state) => Montant::mad($state)),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageEvenements::route('/')];
    }
}
