<?php

namespace App\Filament\Resources\ParkingLocations\Pages;

use App\Filament\Resources\ParkingLocations\ParkingLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageParkingLocations extends ManageRecords
{
    protected static string $resource = ParkingLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
