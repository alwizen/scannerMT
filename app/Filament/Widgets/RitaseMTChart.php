<?php

namespace App\Filament\Widgets;

use App\Models\ScanSession;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class RitaseMTChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Jumlah Ritase MT';

    protected int | string | array $columnSpan = 'full';

    protected ?string $emptyStateHeading = 'No data available';

    protected ?string $pollingInterval = '10s';

    protected ?string $maxHeight = '300px';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        [$startDate, $endDate] = $this->getFilterDateRange();

        $ritase = ScanSession::query()
            ->join('tankers', 'scan_sessions.tanker_id', '=', 'tankers.id')
            ->where('scan_sessions.status', 'completed')
            ->whereBetween('scan_sessions.completed_at', [$startDate, $endDate])
            ->selectRaw('tankers.nopol, COUNT(scan_sessions.id) as total')
            ->groupBy('tankers.id', 'tankers.nopol')
            ->orderByDesc('total')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'NFC Kompartement Status Complete',
                    'data' => $ritase->pluck('total')->map(fn ($total): int => (int) $total)->all(),
                    'backgroundColor' => '#2563eb',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $ritase->pluck('nopol')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'ticks' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }

    private function getFilterDateRange(): array
    {
        $period = $this->pageFilters['period'] ?? 'today';
        $now = Carbon::now();

        return match ($period) {
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            '7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '1_month' => [$now->copy()->subMonth()->startOfDay(), $now->copy()->endOfDay()],
            'custom' => [
                ! empty($this->pageFilters['startDate']) ? Carbon::parse($this->pageFilters['startDate'])->startOfDay() : $now->copy()->startOfDay(),
                ! empty($this->pageFilters['endDate']) ? Carbon::parse($this->pageFilters['endDate'])->endOfDay() : $now->copy()->endOfDay(),
            ],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }
}
