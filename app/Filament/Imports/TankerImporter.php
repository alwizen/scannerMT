<?php

namespace App\Filament\Imports;

use App\Models\Tanker;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class TankerImporter extends Importer
{
    protected static ?string $model = Tanker::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('nopol')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('capacity_kl')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('status')
                ->requiredMapping()
                ->rules(['required']),
        ];
    }

    public function resolveRecord(): Tanker
    {
        return new Tanker();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your tanker import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
