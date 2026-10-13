<?php

namespace App\Filament\Widgets;

use App\Enums\CategorieDocument;
use App\Filament\Resources\DocumentAssocieResource;
use App\Models\DocumentAssocie;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class DocumentsPartages extends TableWidget
{
    protected static ?string $heading = 'Documents';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => DocumentAssocie::query())
            ->defaultSort('date_document', 'desc')
            ->emptyStateHeading('Aucun document partagé pour l’instant')
            ->columns(DocumentAssocieResource::colonnes())
            ->filters([
                Tables\Filters\SelectFilter::make('categorie')->label('Catégorie')->options(CategorieDocument::class),
            ])
            ->actions([DocumentAssocieResource::telecharger()]);
    }
}
