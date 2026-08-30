<?php

namespace App\Filament\Resources\TankerCompartments\Pages;

use App\Filament\Resources\TankerCompartments\TankerCompartmentResource;
use App\Models\TankerCompartment;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTankerCompartments extends ManageRecords
{
    protected static string $resource = TankerCompartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // ->mutateFormDataBeforeCreate(function (array $data): array {
                //     $data['compartment_no'] = 1;
                //     return $data;
                // })
                // ->after(function (TankerCompartment $record, array $data) {
                //     TankerCompartmentResource::saveOtherCompartments($record, $data);
                // }),
        ];
    }
}
