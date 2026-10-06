<?php

namespace App\Filament\Resources;

use App\Enums\ModePaiement;
use App\Enums\TypeCompte;
use App\Enums\TypeDecaissement;
use App\Filament\Concerns\AccesParEcran;
use App\Filament\Resources\DecaissementResource\Pages;
use App\Filament\Support\Enregistrement;
use App\Filament\Support\FiltrePeriode;
use App\Filament\Support\PieceJustificative;
use App\Filament\Support\RegleFinance;
use App\Models\ChargeFixe;
use App\Models\Compte;
use App\Models\Decaissement;
use App\Support\Montant;
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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DecaissementResource extends Resource
{
    use AccesParEcran;

    protected static string $ecran = 'decaissements';

    protected static ?string $model = Decaissement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Décaissements';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'décaissement';

    protected static ?string $navigationLabel = 'Décaissements';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(self::caissier(), fn ($q) => $q->where('type', TypeDecaissement::OperationCaisse));
    }

    public static function getNavigationBadge(): ?string
    {
        $nombre = static::getEloquentQuery()->caisseNonJustifiee()->count();

        return $nombre ? (string) $nombre : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Opérations de caisse non justifiées';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\ToggleButtons::make('type')->label('Nature de la sortie')
                ->options(TypeDecaissement::class)->required()->inline()->live()
                ->default(fn () => self::caissier() ? TypeDecaissement::OperationCaisse->value : TypeDecaissement::ChargeFixe->value)
                ->disabled(fn () => self::caissier())->dehydrated()
                ->afterStateUpdated(function (Set $set, Get $get) {
                    if (self::type($get) === TypeDecaissement::OperationCaisse) {
                        $set('compte_id', Compte::where('actif', true)->where('type', TypeCompte::Caisse)->value('id'));
                        $set('mode', ModePaiement::Especes->value);
                    }
                })
                ->columnSpanFull(),

            Forms\Components\Select::make('charge_fixe_id')->label('Charge fixe')
                ->options(fn () => ChargeFixe::where('actif', true)->orderBy('libelle')->pluck('libelle', 'id'))
                ->searchable()->required()->live()
                ->visible(fn (Get $get) => self::type($get) === TypeDecaissement::ChargeFixe)
                ->afterStateUpdated(function (?string $state, Set $set) {
                    if ($charge = ChargeFixe::find($state)) {
                        $set('montant', $charge->montant);
                        $set('periode', $charge->prochainePeriode());
                    }
                }),
            Forms\Components\TextInput::make('periode')->label('Période réglée')
                ->required()->mask('9999-99')->placeholder('AAAA-MM')
                ->visible(fn (Get $get) => self::type($get) === TypeDecaissement::ChargeFixe)
                ->rule(RegleFinance::decaissement('periode', self::donnees(...))),

            Forms\Components\Select::make('fournisseur_id')->label('Fournisseur')
                ->relationship('fournisseur', 'raison_sociale')
                ->searchable()->preload()->required()
                ->createOptionForm(FournisseurResource::champs())
                ->visible(fn (Get $get) => self::type($get) === TypeDecaissement::Fournisseur),
            Forms\Components\TextInput::make('reference_facture')->label('N° de facture')->maxLength(100)
                ->visible(fn (Get $get) => self::type($get) === TypeDecaissement::Fournisseur),

            Forms\Components\TextInput::make('operateur')->label('Opérateur de caisse')->required()->maxLength(255)
                ->default(fn () => self::caissier() ? auth()->user()->name : null)
                ->visible(fn (Get $get) => self::type($get) === TypeDecaissement::OperationCaisse),
            Forms\Components\TextInput::make('beneficiaire')->label('Bénéficiaire')->maxLength(255)
                ->visible(fn (Get $get) => self::type($get) === TypeDecaissement::OperationCaisse),

            Forms\Components\DatePicker::make('date_operation')->label('Date')->required()->default(now())->maxDate(now()),
            Forms\Components\Select::make('compte_id')->label('Compte débité')->required()->live()
                ->options(fn (Get $get) => CompteResource::options(self::type($get) === TypeDecaissement::OperationCaisse ? TypeCompte::Caisse : null))
                ->default(fn () => Compte::where('actif', true)
                    ->where('type', self::caissier() ? TypeCompte::Caisse : TypeCompte::Banque)->value('id'))
                ->rule(RegleFinance::decaissement('compte_id', self::donnees(...))),
            Forms\Components\Select::make('mode')->label('Mode')->options(ModePaiement::class)->required()
                ->default(fn () => self::caissier() ? ModePaiement::Especes->value : ModePaiement::Virement->value),
            Forms\Components\TextInput::make('montant')->numeric()->required()->suffix('MAD')
                ->rule(RegleFinance::decaissement('montant', self::donnees(...))),
            Forms\Components\Textarea::make('motif')->label('Motif')->rows(2)->maxLength(500)
                ->required(fn (Get $get) => self::type($get) === TypeDecaissement::OperationCaisse)
                ->columnSpanFull(),
            self::champPiece()
                ->helperText(fn (Get $get) => self::type($get) === TypeDecaissement::OperationCaisse
                    ? 'Sans pièce, l’opération reste « non justifiée » et apparaît dans les alertes.' : null)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function champPiece(): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make('piece_justificative')->label('Pièce justificative')
            ->disk(Decaissement::DISQUE)->directory('justificatifs')->visibility('private')
            ->acceptedFileTypes(array_keys(PieceJustificative::TYPES))
            ->rules(['extensions:'.implode(',', [...array_values(PieceJustificative::TYPES), 'jpeg']), fn () => PieceJustificative::regleContenu()])
            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file) => PieceJustificative::nomStockage($file))
            ->maxSize(PieceJustificative::TAILLE_MAX_KO)
            ->openable()->downloadable();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['chargeFixe', 'fournisseur', 'compte']))
            ->defaultSort('date_operation', 'desc')
            ->recordClasses(fn (Decaissement $d) => $d->type === TypeDecaissement::OperationCaisse && ! $d->justifie ? 'pc-fiche-alerte' : null)
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('date_operation')->visibleFrom('md')->label('Date')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('type')->visibleFrom('lg')->label('Nature')->badge()->hidden(fn () => self::caissier()),
                Tables\Columns\TextColumn::make('destinataire')->label('Payé à / pour')
                    ->state(fn (Decaissement $d) => $d->destinataire())
                    ->description(fn (Decaissement $d) => $d->type === TypeDecaissement::Fournisseur && $d->reference_facture
                        ? "Facture {$d->reference_facture}"
                        : ($d->type === TypeDecaissement::OperationCaisse ? $d->beneficiaire : null))
                    ->wrap()
                    ->searchable(query: fn ($query, string $search) => $query
                        ->where('operateur', 'like', "%{$search}%")
                        ->orWhere('motif', 'like', "%{$search}%")
                        ->orWhere('reference_facture', 'like', "%{$search}%")
                        ->orWhereHas('fournisseur', fn ($q) => $q->where('raison_sociale', 'like', "%{$search}%"))
                        ->orWhereHas('chargeFixe', fn ($q) => $q->where('libelle', 'like', "%{$search}%"))),
                Tables\Columns\TextColumn::make('compte.nom')->visibleFrom('lg')->label('Compte')->toggleable(),
                Tables\Columns\TextColumn::make('mode')->visibleFrom('lg')->badge()->color('gray')->toggleable(),
                Tables\Columns\TextColumn::make('montant')->alignEnd()->sortable()->weight('bold')->color('danger')
                    ->formatStateUsing(fn ($state) => Montant::mad($state))
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn ($state) => Montant::mad($state))),
                Tables\Columns\IconColumn::make('justifie')->visibleFrom('sm')->label('Justifiée')->alignCenter()
                    ->state(fn (Decaissement $d) => $d->type === TypeDecaissement::OperationCaisse ? $d->justifie : null)
                    ->boolean()
                    ->trueIcon('heroicon-o-document-check')->falseIcon('heroicon-o-exclamation-triangle')
                    ->falseColor('danger'),
            ])
            ->filters([
                FiltrePeriode::make('date_operation'),
                Tables\Filters\SelectFilter::make('compte')->relationship('compte', 'nom'),
                Tables\Filters\SelectFilter::make('fournisseur')->relationship('fournisseur', 'raison_sociale')
                    ->hidden(fn () => self::caissier()),
            ])
            ->actions([
                Tables\Actions\Action::make('justifier')->label('Justifier')
                    ->icon('heroicon-o-paper-clip')->color('warning')
                    ->visible(fn (Decaissement $d) => $d->type === TypeDecaissement::OperationCaisse && ! $d->justifie)
                    ->modalHeading(fn (Decaissement $d) => "Justifier {$d->numero}")
                    ->modalDescription(fn (Decaissement $d) => $d->destinataire().' — '.Montant::mad($d->montant))
                    ->form([self::champPiece()->required()])
                    ->action(function (Decaissement $record, array $data) {
                        Enregistrement::proteger(fn () => $record->update(['piece_justificative' => $data['piece_justificative']]));
                        Notification::make()->success()->title("{$record->numero} justifiée")->send();
                    }),
                Tables\Actions\Action::make('piece')->label('Pièce')
                    ->icon('heroicon-o-document-arrow-down')->color('gray')
                    ->visible(fn (Decaissement $d) => filled($d->piece_justificative))
                    ->url(fn (Decaissement $d) => route('decaissements.piece', $d), shouldOpenInNewTab: true),
                Tables\Actions\EditAction::make()
                    ->hidden(fn () => self::caissier())
                    ->using(fn (Decaissement $record, array $data) => Enregistrement::proteger(fn () => tap($record)->update($data))),
                Tables\Actions\DeleteAction::make()->hidden(fn () => self::caissier()),
            ]);
    }

    public static function caissier(): bool
    {
        return (bool) auth()->user()?->estCaissier();
    }

    private static function type(Get $get): ?TypeDecaissement
    {
        $type = $get('type');

        return $type instanceof TypeDecaissement ? $type : TypeDecaissement::tryFrom((string) $type);
    }

    private static function donnees(Get $get, $record): array
    {
        return [[
            'type' => self::type($get)?->value,
            'charge_fixe_id' => $get('charge_fixe_id'),
            'periode' => $get('periode'),
            'fournisseur_id' => $get('fournisseur_id'),
            'operateur' => $get('operateur'),
            'compte_id' => $get('compte_id'),
            'montant' => $get('montant'),
        ], $record instanceof Decaissement ? $record->getKey() : null];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDecaissements::route('/')];
    }
}
