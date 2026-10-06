<?php

namespace App\Filament\Resources;

use App\Enums\StatutBonCommande;
use App\Filament\Concerns\AccesParEcran;
use App\Filament\Resources\BonCommandeResource\Pages;
use App\Filament\Support\Avatar;
use App\Filament\Support\FiltrePeriode;
use App\Filament\Support\ImpressionPiece;
use App\Models\BonCommande;
use App\Models\Fournisseur;
use App\Support\Montant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BonCommandeResource extends Resource
{
    use AccesParEcran;

    protected static string $ecran = 'fournisseurs';

    protected static ?string $model = BonCommande::class;

    protected static ?string $slug = 'bons-commande';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Décaissements';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'bon de commande';

    protected static ?string $pluralModelLabel = 'bons de commande';

    protected static ?string $recordTitleAttribute = 'numero';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('fournisseur_id')->label('Fournisseur')
                ->relationship('fournisseur', 'raison_sociale')->searchable()->preload()->required()
                ->createOptionForm(FournisseurResource::champs())
                ->createOptionUsing(fn (array $data) => Fournisseur::create($data)->getKey()),
            Forms\Components\TextInput::make('objet')->maxLength(255)->placeholder('Gros œuvre, menuiserie, fournitures…'),
            Forms\Components\DatePicker::make('date_commande')->label('Date de commande')->required()->default(now())->native(false),
            Forms\Components\DatePicker::make('date_livraison')->label('Livraison prévue')->native(false)
                ->afterOrEqual('date_commande'),
            Forms\Components\TextInput::make('lieu_livraison')->label('Lieu de livraison')->maxLength(255),
            Forms\Components\TextInput::make('conditions_paiement')->label('Conditions de paiement')->maxLength(255)
                ->placeholder('Chèque à 30 jours'),
            Forms\Components\Repeater::make('lignes')->relationship()->orderColumn('ordre')
                ->label('Lignes de commande')->addActionLabel('Ajouter une ligne')
                ->schema([
                    Forms\Components\TextInput::make('reference')->label('Réf.')->maxLength(50)->columnSpan(2),
                    Forms\Components\TextInput::make('designation')->label('Désignation')->required()->maxLength(255)->columnSpan(5),
                    Forms\Components\TextInput::make('quantite')->label('Quantité')->numeric()->required()->minValue(0.01)->default(1)
                        ->live(onBlur: true)->columnSpan(2),
                    Forms\Components\TextInput::make('prix_unitaire')->label('PU HT')->numeric()->required()->minValue(0)
                        ->live(onBlur: true)->columnSpan(3),
                ])
                ->columns(12)->minItems(1)->defaultItems(1)->columnSpanFull(),
            Forms\Components\Select::make('taux_tva')->label('TVA')->required()->default(20)->live()
                ->options([0 => '0 %', 7 => '7 %', 10 => '10 %', 14 => '14 %', 20 => '20 %']),
            Forms\Components\Placeholder::make('totaux')->label('Totaux')
                ->content(function (Get $get): string {
                    $ht = collect($get('lignes') ?? [])->sum(fn ($l) => (float) ($l['quantite'] ?? 0) * (float) ($l['prix_unitaire'] ?? 0));
                    $tva = $ht * (float) $get('taux_tva') / 100;

                    return 'HT '.Montant::mad($ht).' · TVA '.Montant::mad($tva).' · TTC '.Montant::mad($ht + $tva);
                }),
            Forms\Components\ToggleButtons::make('statut')->options(StatutBonCommande::class)->inline()
                ->default(StatutBonCommande::EnCours->value)->required()->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['fournisseur', 'lignes']))
            ->defaultSort('date_commande', 'desc')
            ->recordClasses(fn (BonCommande $b) => match ($b->statut) {
                StatutBonCommande::Livre => 'pc-fiche-ok',
                StatutBonCommande::EnCours => 'pc-fiche-attente',
                default => null,
            })
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('date_commande')->visibleFrom('md')->label('Date')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('fournisseur.raison_sociale')->label('Fournisseur')->searchable()
                    ->formatStateUsing(fn (?string $state) => Avatar::html($state)),
                Tables\Columns\TextColumn::make('objet')->visibleFrom('lg')->limit(40)->toggleable(),
                Tables\Columns\TextColumn::make('total_ttc')->label('Total TTC')->alignEnd()
                    ->state(fn (BonCommande $b) => Montant::mad($b->totalTtc())),
                Tables\Columns\TextColumn::make('statut')->visibleFrom('md')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')->options(StatutBonCommande::class),
                Tables\Filters\SelectFilter::make('fournisseur')->relationship('fournisseur', 'raison_sociale'),
                FiltrePeriode::make('date_commande'),
            ])
            ->actions([
                ImpressionPiece::make('Excel', fn (BonCommande $b) => route('bons-commande.imprimer', $b)),
                Tables\Actions\EditAction::make()->modalWidth('5xl'),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageBonsCommande::route('/')];
    }
}
