<?php

namespace App\Filament\Resources\TankerCompartments\Pages;

use App\Filament\Resources\TankerCompartments\TankerCompartmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTankerCompartments extends ManageRecords
{
    protected static string $resource = TankerCompartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
