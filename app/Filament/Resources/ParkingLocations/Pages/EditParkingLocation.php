<?php

namespace App\Filament\Resources\ParkingLocations\Pages;

use App\Filament\Resources\ParkingLocations\ParkingLocationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditParkingLocation extends EditRecord
{
    protected static string $resource = ParkingLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
