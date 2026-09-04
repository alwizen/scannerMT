<?php

namespace App\Filament\Widgets;

use App\Models\Driver;
use App\Models\ScanLog;
use App\Models\Tanker;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class StatOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected ?string $description = 'Berikut adalah gambaran umum dari beberapa analisis.';

    protected function getHeading(): ?string
    {
        return 'Selamat datang, ' . (Auth::user()?->name ?? 'Admin') . ' 👋';
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Total MT', Tanker::query()->count())
                ->description('Jumlah Mobil Tangki terdaftar')
                ->color('info'),

            Stat::make('Total Driver', Driver::query()->count())
                ->description('Jumlah AMT terdaftar')
                ->color('success'),

            Stat::make(
                'AMT Sudah Scan',
                ScanLog::query()
                    ->when(
                        $this->getFilterDateRange(),
                        fn ($query, array $dateRange) => $query
                            ->where('scanned_at', '>=', $dateRange[0])
                            ->where('scanned_at', '<=', $dateRange[1])
                    )
                    ->distinct('driver_id')
                    ->count('driver_id')
            )
                ->description('AMT yang memiliki riwayat scan')
                ->color('warning'),
        ];
    }

    private function getFilterDateRange(): ?array
    {
        $period = $this->pageFilters['period'] ?? 'today';
        $now = Carbon::now();

        return match ($period) {
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            '7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '1_month' => [$now->copy()->subMonth()->startOfDay(), $now->copy()->endOfDay()],
            'custom' => [
                ! empty($this->pageFilters['startDate']) ? Carbon::parse($this->pageFilters['startDate'])->startOfDay() : null,
                ! empty($this->pageFilters['endDate']) ? Carbon::parse($this->pageFilters['endDate'])->endOfDay() : null,
            ],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }
}
