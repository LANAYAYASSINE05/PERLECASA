<?php

namespace App\Filament\Resources;

use App\Enums\StatutDemande;
use App\Enums\TypeDemande;
use App\Filament\Concerns\AccesParEcran;
use App\Filament\Resources\DemandeAssocieResource\Pages;
use App\Models\DemandeAssocie;
use App\Support\Montant;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DemandeAssocieResource extends Resource
{
    use AccesParEcran;

    protected static string $ecran = 'associes';

    protected static ?string $model = DemandeAssocie::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $modelLabel = 'demande d’associé';

    protected static ?string $pluralModelLabel = 'demandes d’associés';

    protected static ?string $navigationLabel = 'Demandes des associés';

    protected static ?string $slug = 'demandes-associes';

    public static function getNavigationBadge(): ?string
    {
        $nombre = DemandeAssocie::where('statut', StatutDemande::EnAttente)->count();

        return $nombre ? (string) $nombre : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('associe'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('associe.name')->label('Associé')->searchable()->weight('bold'),
                ...self::colonnes(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')->options(StatutDemande::class)->default(StatutDemande::EnAttente->value),
                Tables\Filters\SelectFilter::make('type')->options(TypeDemande::class),
            ])
            ->actions([
                Tables\Actions\Action::make('traiter')->label('Répondre')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')->color('primary')
                    ->authorize(fn (DemandeAssocie $record) => auth()->user()->can('update', $record))
                    ->modalHeading(fn (DemandeAssocie $d) => "Demande de {$d->associe?->name}")
                    ->modalDescription(fn (DemandeAssocie $d) => $d->message)
                    ->fillForm(fn (DemandeAssocie $d) => [
                        'statut' => $d->statut === StatutDemande::EnAttente ? StatutDemande::Acceptee->value : $d->statut->value,
                        'reponse' => $d->reponse,
                    ])
                    ->form([
                        Forms\Components\ToggleButtons::make('statut')->label('Décision')->inline()->required()
                            ->options([
                                StatutDemande::Acceptee->value => StatutDemande::Acceptee->getLabel(),
                                StatutDemande::Refusee->value => StatutDemande::Refusee->getLabel(),
                            ])
                            ->colors([StatutDemande::Acceptee->value => 'success', StatutDemande::Refusee->value => 'danger']),
                        Forms\Components\Textarea::make('reponse')->label('Réponse à l’associé')->required()->rows(4)->maxLength(2000),
                    ])
                    ->action(function (DemandeAssocie $record, array $data): void {
                        $record->traiter(StatutDemande::from($data['statut']), $data['reponse']);
                        Notification::make()->success()->title('Réponse envoyée à '.$record->associe?->name)->send();
                    }),
            ]);
    }

    /** Colonnes communes à la gestion (administrateur) et au suivi (associé). */
    public static function colonnes(): array
    {
        return [
            Tables\Columns\TextColumn::make('objet')->searchable()->wrap()
                ->description(fn (DemandeAssocie $d) => str($d->message)->limit(90)),
            Tables\Columns\TextColumn::make('type')->badge()->color('gray')->visibleFrom('md'),
            Tables\Columns\TextColumn::make('montant')->alignEnd()->placeholder('—')->visibleFrom('lg')
                ->formatStateUsing(fn ($state) => Montant::mad($state)),
            Tables\Columns\TextColumn::make('created_at')->label('Envoyée le')->date('d/m/Y')->sortable()->visibleFrom('md'),
            Tables\Columns\TextColumn::make('statut')->badge(),
            Tables\Columns\TextColumn::make('reponse')->label('Réponse')->placeholder('—')->wrap()->limit(120)->visibleFrom('lg'),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDemandesAssocies::route('/')];
    }
}
