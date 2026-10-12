<?php

namespace App\Filament\Resources;

use App\Enums\CategorieDocument;
use App\Filament\Concerns\AccesParEcran;
use App\Filament\Resources\DocumentAssocieResource\Pages;
use App\Filament\Support\DocumentPartage;
use App\Models\DocumentAssocie;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DocumentAssocieResource extends Resource
{
    use AccesParEcran;

    protected static string $ecran = 'associes';

    protected static ?string $model = DocumentAssocie::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $modelLabel = 'document associé';

    protected static ?string $pluralModelLabel = 'documents associés';

    protected static ?string $navigationLabel = 'Documents associés';

    protected static ?string $slug = 'documents-associes';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('titre')->required()->maxLength(255)->columnSpanFull()
                ->placeholder('PV de l’AG ordinaire 2026, bilan 2025…'),
            Forms\Components\Select::make('categorie')->label('Catégorie')->options(CategorieDocument::class)->required()
                ->default(CategorieDocument::ProcesVerbal->value),
            Forms\Components\DatePicker::make('date_document')->label('Date du document')->required()->default(now())->maxDate(now()),
            Forms\Components\FileUpload::make('fichier')->label('Fichier')->required()
                ->disk(DocumentAssocie::DISQUE)->directory(DocumentAssocie::DOSSIER)->visibility('private')
                ->acceptedFileTypes(array_keys(DocumentPartage::TYPES))
                ->rules(['extensions:'.implode(',', [...array_values(DocumentPartage::TYPES), 'jpeg']), fn () => DocumentPartage::regleContenu()])
                ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file) => DocumentPartage::nomStockage($file))
                ->storeFileNamesIn('nom_original')
                ->maxSize(DocumentPartage::TAILLE_MAX_KO)
                ->helperText('PDF, image, Excel ou Word, 10 Mo au plus. Tous les associés sont prévenus à l’ajout.')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_document', 'desc')
            ->columns(self::colonnes())
            ->filters([
                Tables\Filters\SelectFilter::make('categorie')->label('Catégorie')->options(CategorieDocument::class),
            ])
            ->actions([
                self::telecharger(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    /** Colonnes communes à la gestion (administrateur) et à la consultation (associé). */
    public static function colonnes(): array
    {
        return [
            Tables\Columns\TextColumn::make('titre')->searchable()->weight('bold')->wrap(),
            Tables\Columns\TextColumn::make('categorie')->label('Catégorie')->badge(),
            Tables\Columns\TextColumn::make('date_document')->label('Date')->date('d/m/Y')->sortable(),
        ];
    }

    public static function telecharger(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('telecharger')->label('Télécharger')
            ->icon('heroicon-o-arrow-down-tray')->color('success')
            ->url(fn (DocumentAssocie $d) => route('documents-associes.telecharger', $d));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDocumentsAssocies::route('/')];
    }
}
