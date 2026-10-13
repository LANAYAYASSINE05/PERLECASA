<?php

namespace App\Filament\Pages;

use App\Enums\TypeDemande;
use App\Filament\Resources\DemandeAssocieResource;
use App\Filament\Support\PageAssocie;
use App\Models\DemandeAssocie;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class DemandesAssocie extends PageAssocie implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.associe-tableau';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $title = 'Mes demandes';

    protected static ?string $slug = 'espace-associe/demandes';

    protected static ?int $navigationSort = 7;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nouvelle demande')
                ->model(DemandeAssocie::class)
                ->modalHeading('Nouvelle demande à l’administrateur')
                ->form([
                    Forms\Components\ToggleButtons::make('type')->label('Type')->options(TypeDemande::class)
                        ->required()->inline()->live()->default(TypeDemande::Information->value),
                    Forms\Components\TextInput::make('objet')->required()->maxLength(255),
                    Forms\Components\TextInput::make('montant')->label('Montant demandé')->numeric()->minValue(1)->suffix('MAD')
                        ->required(fn (Get $get) => $get('type') === TypeDemande::Remboursement->value)
                        ->visible(fn (Get $get) => $get('type') === TypeDemande::Remboursement->value),
                    Forms\Components\Textarea::make('message')->required()->rows(5)->maxLength(2000),
                ])
                ->mutateFormDataUsing(fn (array $data) => [
                    ...$data,
                    'montant' => $data['type'] === TypeDemande::Remboursement->value ? ($data['montant'] ?? null) : null,
                ])
                ->successNotificationTitle('Demande envoyée à l’administrateur'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(DemandeAssocie::query()->where('associe_id', auth()->id()))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Aucune demande envoyée')
            ->columns(DemandeAssocieResource::colonnes());
    }
}
