<?php

namespace App\Filament\Resources\ProgrammeResource\Pages;

use App\Filament\Resources\ProgrammeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageProgrammes extends ManageRecords
{
    protected static string $resource = ProgrammeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
