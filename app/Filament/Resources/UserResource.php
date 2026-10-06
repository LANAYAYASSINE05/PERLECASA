<?php

namespace App\Filament\Resources;

use App\Enums\Role;
use App\Filament\Concerns\AccesParEcran;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    use AccesParEcran;

    protected static string $ecran = 'utilisateurs';

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $modelLabel = 'utilisateur';

    protected static ?string $slug = 'utilisateurs';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nom complet')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->label('E-mail')->email()->required()->unique(ignoreRecord: true),
            Forms\Components\Select::make('role')->label('Rôle')->options(Role::class)->required()->default(Role::Comptable->value),
            Forms\Components\TextInput::make('password')->label('Mot de passe')->password()->revealable()
                ->rule(Password::default())
                ->helperText(fn (string $operation) => $operation === 'edit'
                    ? 'Laisser vide pour ne pas le changer.'
                    : '10 caractères minimum, avec majuscules, minuscules et chiffres.')
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state)),
            Forms\Components\Toggle::make('actif')->default(true)
                ->disabled(fn (?User $record) => $record?->is(auth()->user())),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nom')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('email')->visibleFrom('md')->label('E-mail')->searchable(),
                Tables\Columns\TextColumn::make('role')->label('Rôle')->badge(),
                Tables\Columns\IconColumn::make('actif')->visibleFrom('md')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->hidden(fn (User $u) => $u->is(auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUsers::route('/')];
    }
}
