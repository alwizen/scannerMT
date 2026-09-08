<?php

namespace App\Filament\Resources\Tankers\Pages;

use App\Filament\Resources\Tankers\TankerResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageTankers extends ManageRecords
{
    protected static string $resource = TankerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
            ->after(function () {
                    Notification::make()
                        ->title('Tanker berhasil ditambahkan')
                        ->success()
                        ->sendToDatabase(auth()->user());
                }),
            ImportAction::make()
                ->importer(\App\Filament\Imports\TankerImporter::class)
                ->label('Import Tanker'),
        ];
    }
}
