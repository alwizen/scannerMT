<?php

namespace App\Filament\Widgets;

use App\Models\Driver;
use App\Models\ScanLog;
use App\Models\Tanker;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Overview';

    protected ?string $description = 'An overview of some analytics.';

    protected function getStats(): array
    {
        return [
            Stat::make('Total MT', Tanker::query()->count())
                ->description('Jumlah tanker terdaftar')
                ->color('info'),

            Stat::make('Total Driver', Driver::query()->count())
                ->description('Jumlah driver terdaftar')
                ->color('success'),

            Stat::make(
                'Driver Sudah Scan',
                ScanLog::query()->distinct('driver_id')->count('driver_id')
            )
                ->description('Driver yang memiliki riwayat scan')
                ->color('warning'),
        ];
    }
}
