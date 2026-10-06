<?php

namespace App\Filament\Resources\ChargeFixeResource\Pages;

use App\Filament\Resources\ChargeFixeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageChargeFixes extends ManageRecords
{
    protected static string $resource = ChargeFixeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
