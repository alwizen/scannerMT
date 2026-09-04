<?php

namespace App\Filament\Widgets;

use App\Models\ScanLog;
use App\Models\TankerCompartment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ScanMTTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Realtime Monitoring Pretrip Mainhole';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected static array $scansCache = [];

    protected function getScansForRecord(ScanLog $record)
    {
        $key = "{$record->driver_id}_{$record->tanker_id}_{$record->scan_date}";

        if (! isset(static::$scansCache[$key])) {
            static::$scansCache[$key] = ScanLog::query()
                ->where('driver_id', $record->driver_id)
                ->whereDate('scanned_at', $record->scan_date)
                ->whereHas('tankerCompartment', fn ($q) => $q->where('tanker_id', $record->tanker_id))
                ->with(['tankerCompartment', 'parkingLocation'])
                ->get()
                ->keyBy(fn ($item) => $item->tankerCompartment?->compartment_no);
        }

        return static::$scansCache[$key];
    }

    public function table(Table $table): Table
    {
        $maxCompartments = max(4, TankerCompartment::max('compartment_no') ?? 4);

        $columns = [
            TextColumn::make('driver.name')
                ->label('Nama AMT')
                ->description(fn (ScanLog $record): string => match ($record->driver?->role) {
                    'driver' => 'AMT 1',
                    'helper' => 'AMT 2',
                    default => '-',
                })
                ->sortable()
                ->searchable(),

            TextColumn::make('nopol')
                ->label('Nopol MT')
                ->badge()
                ->color('info')
                ->searchable(),

            TextColumn::make('capacity_kl')
                ->label('Kapasitas')
                ->formatStateUsing(fn ($state) => $state ? $state . ' KL' : '-')
                ->sortable(),

            TextColumn::make('device.name')
                ->label('Device')
                ->badge()
                ->color('gray'),

            TextColumn::make('location')
                ->label('Lokasi (lat & long)')
                ->getStateUsing(function (ScanLog $record) {
                    if ($record->latitude && $record->longitude) {
                        return "{$record->latitude}, {$record->longitude}";
                    }
                    return '-';
                })
                ->description(function (ScanLog $record) {
                    if (! $record->latitude || ! $record->longitude) {
                        return null;
                    }
                    return $record->is_inside_geofence
                        ? 'Di Dalam Area'
                        : 'Di Luar Area';
                }),
        ];

        for ($i = 1; $i <= $maxCompartments; $i++) {
            $compNo = $i;
            $columns[] = TextColumn::make("komp_{$compNo}")
                ->label("Komp {$compNo}")
                ->badge(fn (ScanLog $record) => $this->getScansForRecord($record)->has($compNo))
                ->getStateUsing(function (ScanLog $record) use ($compNo) {
                    $scans = $this->getScansForRecord($record);
                    $compLog = $scans->get($compNo);

                    if (! $compLog || ! $compLog->scanned_at) {
                        return '-';
                    }

                    return Carbon::parse($compLog->scanned_at)->format('H:i:s');
                })
                ->color(function (ScanLog $record) use ($compNo) {
                    $scans = $this->getScansForRecord($record);
                    return $scans->has($compNo) ? 'success' : 'gray';
                });
        }

        $columns[] = TextColumn::make('status')
            ->label('Status')
            ->badge()
            ->getStateUsing(function (ScanLog $record) {
                $scans = $this->getScansForRecord($record);
                $totalComps = TankerCompartment::where('tanker_id', $record->tanker_id)->count();
                $scannedCount = $scans->count();

                return ($totalComps > 0 && $scannedCount >= $totalComps) ? 'Done' : 'Kurang';
            })
            ->color(fn (string $state): string => match ($state) {
                'Done' => 'success',
                'Kurang' => 'warning',
                default => 'gray',
            });

        $columns[] = TextColumn::make('last_update')
            ->label('Last Update')
            ->getStateUsing(function (ScanLog $record) {
                return $record->last_update
                    ? Carbon::parse($record->last_update)->format('d M Y H:i:s')
                    : '-';
            });

        return $table
            ->poll('3s')
            ->paginated([25, 50, 100])
            ->query(function (): Builder {
                [$startDate, $endDate] = $this->getFilterDateRange();

                return ScanLog::query()
                    ->when($startDate, fn (Builder $query) => $query->where('scan_logs.scanned_at', '>=', $startDate))
                    ->when($endDate, fn (Builder $query) => $query->where('scan_logs.scanned_at', '<=', $endDate))
                    ->join('tanker_compartments', 'scan_logs.tanker_compartment_id', '=', 'tanker_compartments.id')
                    ->join('tankers', 'tanker_compartments.tanker_id', '=', 'tankers.id')
                    ->select([
                        DB::raw('MAX(scan_logs.id) as id'),
                        'scan_logs.driver_id',
                        'tanker_compartments.tanker_id',
                        'tankers.nopol as nopol',
                        'tankers.capacity_kl as capacity_kl',
                        DB::raw('DATE(scan_logs.scanned_at) as scan_date'),
                        DB::raw('MAX(scan_logs.device_id) as device_id'),
                        DB::raw('MAX(scan_logs.scanned_at) as last_update'),
                        DB::raw('MAX(scan_logs.latitude) as latitude'),
                        DB::raw('MAX(scan_logs.longitude) as longitude'),
                        DB::raw('MAX(scan_logs.is_inside_geofence) as is_inside_geofence'),
                        DB::raw('MAX(scan_logs.parking_location_id) as parking_location_id'),
                    ])
                    ->groupBy([
                        'scan_logs.driver_id',
                        'tanker_compartments.tanker_id',
                        'tankers.nopol',
                        'tankers.capacity_kl',
                        DB::raw('DATE(scan_logs.scanned_at)'),
                    ])
                    ->orderByDesc('last_update');
            })
            ->columns($columns);
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
                ! empty($this->pageFilters['startDate']) ? Carbon::parse($this->pageFilters['startDate'])->startOfDay() : null,
                ! empty($this->pageFilters['endDate']) ? Carbon::parse($this->pageFilters['endDate'])->endOfDay() : null,
            ],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }
}
