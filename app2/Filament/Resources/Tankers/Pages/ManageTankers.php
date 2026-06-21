<?php

namespace App\Filament\Resources\Tankers\Pages;

use App\Filament\Resources\Tankers\TankerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTankers extends ManageRecords
{
    protected static string $resource = TankerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
