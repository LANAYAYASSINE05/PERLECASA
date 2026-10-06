<?php

namespace App\Filament\Resources;

use App\Enums\TypeEncaissement;
use App\Filament\Concerns\AccesParEcran;
use App\Filament\Resources\VenteResource\Pages;
use App\Filament\Support\Avatar;
use App\Filament\Support\Enregistrement;
use App\Filament\Support\FiltrePeriode;
use App\Filament\Support\ImpressionPiece;
use App\Filament\Support\Jauge;
use App\Filament\Support\RegleFinance;
use App\Models\Appartement;
use App\Models\Client;
use App\Models\Encaissement;
use App\Models\Vente;
use App\Support\Montant;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VenteResource extends Resource
{
    use AccesParEcran;

    protected static string $ecran = 'ventes';

    protected static ?string $model = Vente::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Encaissements';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'vente';

    protected static ?string $navigationLabel = 'Appartements vendus';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('appartement_id')->label('Appartement')
                ->options(fn (?Vente $record) => Appartement::query()
                    ->with('programme')
                    ->where(fn ($q) => $q->disponibles()->when($record, fn ($q) => $q->orWhere('id', $record->appartement_id)))
                    ->orderBy('reference')
                    ->get()
                    ->mapWithKeys(fn (Appartement $a) => [$a->id => $a->libelle().' — '.Montant::mad($a->prix)]))
                ->searchable()->required()->live()
                ->disabledOn('edit')
                ->afterStateUpdated(fn (?string $state, Set $set) => $set('prix_vente', Appartement::find($state)?->prix))
                ->columnSpanFull(),
            Forms\Components\Select::make('client_id')->label('Client')
                ->relationship('client', 'nom')
                ->getOptionLabelFromRecordUsing(fn (Client $c) => $c->nomComplet().($c->cin ? " ({$c->cin})" : ''))
                ->searchable(['nom', 'prenom', 'cin'])->preload()->required()
                ->createOptionForm(ClientResource::champs())
                ->columnSpanFull(),
            Forms\Components\DatePicker::make('date_vente')->label('Date de vente')->required()->default(now())->maxDate(now()),
            Forms\Components\TextInput::make('prix_vente')->label('Prix de vente')->numeric()->required()->minValue(1)->suffix('MAD')
                ->rule(fn (?Vente $record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
                    if ($record && (float) $value + 0.001 < $record->encaisse()) {
                        $fail('Le prix ne peut pas être inférieur au montant déjà encaissé ('.Montant::mad($record->encaisse()).').');
                    }
                }),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['appartement.programme', 'client'])->withSum('encaissements', 'montant'))
            ->defaultSort('date_vente', 'desc')
            ->recordClasses(fn (Vente $v) => self::reste($v) > 0.001 ? 'pc-fiche-attente' : 'pc-fiche-ok')
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('date_vente')->visibleFrom('md')->label('Date')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('appartement.reference')->visibleFrom('md')->label('Appartement')
                    ->description(fn (Vente $v) => "{$v->appartement?->programme?->nom} · {$v->appartement?->typologie}")
                    ->searchable(),
                Tables\Columns\TextColumn::make('client.nom')->label('Client')
                    ->formatStateUsing(fn (Vente $v) => Avatar::html($v->client?->nomComplet(), $v->client?->telephone))
                    ->searchable(['nom', 'prenom']),
                Tables\Columns\TextColumn::make('prix_vente')->visibleFrom('lg')->label('Prix')->alignEnd()->sortable()
                    ->formatStateUsing(fn ($state) => Montant::mad($state))
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn ($state) => Montant::mad($state))),
                Tables\Columns\TextColumn::make('encaissements_sum_montant')->visibleFrom('lg')->label('Encaissé')->alignEnd()->color('success')->default(0)
                    ->formatStateUsing(fn ($state) => Montant::mad($state))
                    ->description(fn (Vente $v) => $v->prix_vente > 0 ? Jauge::html((float) $v->encaissements_sum_montant * 100 / (float) $v->prix_vente) : null),
                Tables\Columns\TextColumn::make('reste')->label('Reste dû')->alignEnd()->weight('bold')
                    ->state(fn (Vente $v) => self::reste($v))
                    ->formatStateUsing(fn ($state) => Montant::mad($state))
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('statut_paiement')->visibleFrom('md')->label('Statut')->badge()
                    ->state(fn (Vente $v) => self::reste($v) > 0.001 ? 'En cours' : 'Soldée')
                    ->color(fn (string $state) => $state === 'Soldée' ? 'success' : 'warning'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('soldee')->label('Paiement')
                    ->placeholder('Toutes')->trueLabel('Soldées')->falseLabel('Reste à encaisser')
                    ->queries(
                        true: fn (Builder $q) => $q->whereRaw('prix_vente <= (select coalesce(sum(montant), 0) from encaissements where encaissements.vente_id = ventes.id)'),
                        false: fn (Builder $q) => $q->whereRaw('prix_vente > (select coalesce(sum(montant), 0) from encaissements where encaissements.vente_id = ventes.id)'),
                    ),
                Tables\Filters\SelectFilter::make('programme')
                    ->relationship('appartement.programme', 'nom'),
                FiltrePeriode::make('date_vente'),
            ])
            ->actions([
                Tables\Actions\Action::make('encaisser')->label('Encaisser')
                    ->icon('heroicon-o-banknotes')->color('success')
                    ->visible(fn (Vente $v) => self::reste($v) > 0.001 && auth()->user()->accede('encaissements'))
                    ->modalHeading(fn (Vente $v) => "Encaisser — {$v->libelle()}")
                    ->modalDescription(fn (Vente $v) => 'Reste dû : '.Montant::mad(self::reste($v)))
                    ->fillForm(fn (Vente $v) => ['montant' => self::reste($v)])
                    ->form([
                        ...EncaissementResource::champsPaiement(),
                        Forms\Components\TextInput::make('montant')->numeric()->required()->suffix('MAD')
                            ->rule(RegleFinance::encaissement('montant', fn (Get $get, Vente $record) => [[
                                'type' => TypeEncaissement::VenteAppartement->value,
                                'vente_id' => $record->id,
                                'compte_id' => $get('compte_id'),
                            ], null])),
                        Forms\Components\TextInput::make('libelle')->label('Libellé')->maxLength(255)->placeholder('Avance, 2e tranche, solde…'),
                    ])
                    ->action(function (Vente $record, array $data) {
                        $encaissement = Enregistrement::proteger(fn () => Encaissement::create([
                            ...$data,
                            'type' => TypeEncaissement::VenteAppartement,
                            'vente_id' => $record->id,
                        ]));

                        Notification::make()->success()
                            ->title("Encaissement {$encaissement->numero} enregistré")
                            ->body('Reste dû : '.Montant::mad($record->resteDu()))
                            ->send();
                    }),
                ImpressionPiece::make('Facture', fn (Vente $v) => route('ventes.facture', $v)),
                Tables\Actions\EditAction::make()
                    ->using(fn (Vente $record, array $data) => Enregistrement::proteger(fn () => tap($record)->update($data))),
                Tables\Actions\DeleteAction::make()->label('Annuler la vente')
                    ->modalDescription('L’appartement redeviendra disponible.')
                    ->hidden(fn (Vente $v) => $v->encaissements_sum_montant !== null)
                    ->using(fn (Vente $record) => Enregistrement::proteger(fn () => $record->delete())),
            ]);
    }

    private static function reste(Vente $v): float
    {
        return round((float) $v->prix_vente - (float) ($v->encaissements_sum_montant ?? $v->encaisse()), 2);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageVentes::route('/')];
    }
}
