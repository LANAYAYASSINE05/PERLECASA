<?php

namespace App\Filament\Pages;

use App\Enums\CategorieDocument;
use App\Filament\Resources\DocumentAssocieResource;
use App\Filament\Support\PageAssocie;
use App\Models\DocumentAssocie;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class DocumentsAssocie extends PageAssocie implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.associe-tableau';

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $title = 'Documents';

    protected static ?string $slug = 'espace-associe/documents';

    protected static ?int $navigationSort = 5;

    public function table(Table $table): Table
    {
        return $table
            ->query(DocumentAssocie::query())
            ->defaultSort('date_document', 'desc')
            ->emptyStateHeading('Aucun document partagé pour l’instant')
            ->columns(DocumentAssocieResource::colonnes())
            ->filters([
                Tables\Filters\SelectFilter::make('categorie')->label('Catégorie')->options(CategorieDocument::class),
            ])
            ->actions([DocumentAssocieResource::telecharger()]);
    }
}
