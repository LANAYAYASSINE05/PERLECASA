<?php

namespace App\Filament\Resources;

use App\Enums\ModePaiement;
use App\Enums\TypeCompte;
use App\Enums\TypeEncaissement;
use App\Filament\Concerns\AccesParEcran;
use App\Filament\Resources\EncaissementResource\Pages;
use App\Filament\Support\Enregistrement;
use App\Filament\Support\FiltrePeriode;
use App\Filament\Support\ImpressionPiece;
use App\Filament\Support\RegleFinance;
use App\Models\Compte;
use App\Models\Encaissement;
use App\Models\Vente;
use App\Support\Montant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;

class EncaissementResource extends Resource
{
    use AccesParEcran;

    protected static string $ecran = 'encaissements';

    protected static ?string $model = Encaissement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = 'Encaissements';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'encaissement';

    protected static ?string $navigationLabel = 'Encaissements';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\ToggleButtons::make('type')->label('Origine de l’argent')
                ->options(TypeEncaissement::class)->required()->inline()->live()
                ->default(TypeEncaissement::VenteAppartement->value)
                ->columnSpanFull(),
            Forms\Components\Select::make('vente_id')->label('Vente d’appartement')
                ->options(fn (?Encaissement $record) => self::ventesAEncaisser($record?->vente_id))
                ->searchable()->required()->live()
                ->visible(fn (Get $get) => self::type($get) === TypeEncaissement::VenteAppartement)
                ->afterStateUpdated(function (?string $state, Set $set, ?Encaissement $record) {
                    if ($vente = Vente::find($state)) {
                        $set('montant', $vente->resteDu($record?->getKey()));
                    }
                })
                ->rule(RegleFinance::encaissement('vente_id', self::donnees(...)))
                ->columnSpanFull(),
            Forms\Components\TextInput::make('emetteur')->label('Émetteur du virement')->maxLength(255)
                ->required()->placeholder('Client, notaire, banque…')
                ->visible(fn (Get $get) => self::type($get) === TypeEncaissement::VirementRecu)
                ->columnSpanFull(),
            ...self::champsPaiement(),
            Forms\Components\TextInput::make('montant')->numeric()->required()->suffix('MAD')
                ->helperText(function (Get $get, ?Encaissement $record) {
                    $vente = self::type($get) === TypeEncaissement::VenteAppartement ? Vente::find($get('vente_id')) : null;

                    return $vente ? 'Reste dû : '.Montant::mad($vente->resteDu($record?->getKey())) : null;
                })
                ->rule(RegleFinance::encaissement('montant', self::donnees(...))),
            Forms\Components\TextInput::make('libelle')->label('Libellé')->maxLength(255)->columnSpanFull()
                ->placeholder('Avance, 2e tranche, solde…'),
        ])->columns(2);
    }

    /** Date, compte, mode et référence : communs au formulaire et à l'action « Encaisser » d'une vente. */
    public static function champsPaiement(): array
    {
        return [
            Forms\Components\DatePicker::make('date_operation')->label('Date')->required()->default(now())->maxDate(now()),
            Forms\Components\Select::make('compte_id')->label('Compte crédité')->required()
                ->options(fn () => CompteResource::options())
                ->default(fn () => Compte::where('actif', true)->where('type', TypeCompte::Banque)->value('id')),
            Forms\Components\Select::make('mode')->label('Mode')->options(ModePaiement::class)->required()
                ->default(ModePaiement::Virement->value),
            Forms\Components\TextInput::make('reference')->label('Référence')->maxLength(100)
                ->placeholder('N° de virement ou de chèque'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['vente.appartement.programme', 'vente.client', 'compte']))
            ->defaultSort('date_operation', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('date_operation')->visibleFrom('md')->label('Date')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('type')->visibleFrom('lg')->label('Origine')->badge(),
                Tables\Columns\TextColumn::make('provenance')->label('Provenance')
                    ->state(fn (Encaissement $e) => $e->provenance())
                    ->description(fn (Encaissement $e) => $e->libelle)
                    ->searchable(query: fn ($query, string $search) => $query
                        ->where('emetteur', 'like', "%{$search}%")
                        ->orWhereHas('vente', fn ($q) => $q->where('numero', 'like', "%{$search}%")
                            ->orWhereHas('client', fn ($c) => $c->where('nom', 'like', "%{$search}%"))
                            ->orWhereHas('appartement', fn ($a) => $a->where('reference', 'like', "%{$search}%")))),
                Tables\Columns\TextColumn::make('compte.nom')->visibleFrom('lg')->label('Compte')->toggleable(),
                Tables\Columns\TextColumn::make('mode')->visibleFrom('lg')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('reference')->visibleFrom('lg')->label('Réf.')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('montant')->alignEnd()->sortable()->weight('bold')->color('success')
                    ->formatStateUsing(fn ($state) => Montant::mad($state))
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn ($state) => Montant::mad($state))),
            ])
            ->filters([
                FiltrePeriode::make('date_operation'),
                Tables\Filters\SelectFilter::make('compte')->relationship('compte', 'nom'),
                Tables\Filters\SelectFilter::make('mode')->options(ModePaiement::class),
            ])
            ->actions([
                ImpressionPiece::make('Reçu', fn (Encaissement $e) => route('encaissements.recu', $e)),
                Tables\Actions\EditAction::make()
                    ->using(fn (Encaissement $record, array $data) => Enregistrement::proteger(fn () => tap($record)->update($data))),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    /** @return array<int, string> ventes qui restent à encaisser (plus celle déjà liée en modification) */
    public static function ventesAEncaisser(?int $inclure = null): array
    {
        return Vente::query()
            ->with(['appartement', 'client'])
            ->withSum('encaissements', 'montant')
            ->latest('date_vente')
            ->get()
            ->filter(fn (Vente $v) => $v->id === $inclure || (float) $v->prix_vente - (float) $v->encaissements_sum_montant > 0.001)
            ->mapWithKeys(fn (Vente $v) => [$v->id => $v->libelle().' — reste '.Montant::mad((float) $v->prix_vente - (float) $v->encaissements_sum_montant)])
            ->all();
    }

    private static function type(Get $get): ?TypeEncaissement
    {
        $type = $get('type');

        return $type instanceof TypeEncaissement ? $type : TypeEncaissement::tryFrom((string) $type);
    }

    private static function donnees(Get $get, $record): array
    {
        return [[
            'type' => self::type($get)?->value,
            'vente_id' => $get('vente_id'),
            'emetteur' => $get('emetteur'),
            'compte_id' => $get('compte_id'),
            'montant' => $get('montant'),
        ], $record instanceof Encaissement ? $record->getKey() : null];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageEncaissements::route('/')];
    }
}
