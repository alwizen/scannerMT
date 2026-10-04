<?php

namespace App\Filament\Widgets;

use App\Models\ScanLog;
use App\Models\ScanSession;
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

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected static array $scansCache = [];

    protected function getScansForRecord(ScanLog $record)
    {
        $sessionKey = $record->scan_session_id ?? 'legacy';
        $key = "{$record->driver_id}_{$record->tanker_id}_{$record->scan_date}_{$sessionKey}";

        if (! isset(static::$scansCache[$key])) {
            $query = ScanLog::query()
                ->where('driver_id', $record->driver_id)
                ->whereDate('scanned_at', $record->scan_date)
                ->whereHas('tankerCompartment', fn($q) => $q->where('tanker_id', $record->tanker_id));

            if ($record->scan_session_id) {
                $query->where('scan_session_id', $record->scan_session_id);
            } else {
                $query->whereNull('scan_session_id');
            }

            static::$scansCache[$key] = $query
                ->with(['tankerCompartment', 'parkingLocation'])
                ->get()
                ->keyBy(fn($item) => $item->tankerCompartment?->compartment_no);
        }

        return static::$scansCache[$key];
    }

    protected function recordNeedsAction(ScanLog $record): bool
    {
        return $this->getScansForRecord($record)
            ->contains(fn($scan) => in_array(
                $scan->content_status,
                ScanSession::ACTION_REQUIRED_CONTENT_STATUSES,
                true,
            ));
    }

    protected function formatContentStatus(?string $status): string
    {
        return match ($status) {
            'sisa_minyak' => 'Sisa Minyak',
            'kosong' => 'Kosong',
            'air' => 'Air',
            'lainnya' => 'Lainnya',
            default => '-',
        };
    }

    protected function contentStatusColor(?string $status): string
    {
        return match ($status) {
            'air', 'sisa_minyak' => 'danger',
            'kosong' => 'success',
            default => 'gray',
        };
    }

    protected function recordActionNote(ScanLog $record): ?string
    {
        if (filled($record->action_note)) {
            return $record->action_note;
        }

        if ($record->scan_session_id) {
            return ScanSession::whereKey($record->scan_session_id)->value('action_note');
        }

        return null;
    }

    protected function recordActionHandled(ScanLog $record): bool
    {
        if (filled($record->action_note) || filled($record->action_handled_at)) {
            return true;
        }

        if ($record->scan_session_id) {
            return ScanSession::whereKey($record->scan_session_id)
                ->where(function ($query) {
                    $query->whereNotNull('action_note')
                        ->orWhereNotNull('action_handled_at');
                })
                ->exists();
        }

        return false;
    }

    public function table(Table $table): Table
    {
        $maxCompartments = max(4, TankerCompartment::max('compartment_no') ?? 4);

        $columns = [
            TextColumn::make('index')
                ->label('#')
                ->rowIndex()
                ->width('w-12')
                ->sortable(false),
            TextColumn::make('driver.name')
                ->label('Nama AMT')
                ->description(fn(ScanLog $record): string => match ($record->driver?->role) {
                    'driver' => 'AMT 1',
                    'helper' => 'AMT 2',
                    default => '-',
                })
                ->searchable(),

            TextColumn::make('scan_session_id')
                ->label('Session ID')
                ->badge()
                ->color('warning')
                ->getStateUsing(fn(ScanLog $record) => $record->scan_session_id ? "#{$record->scan_session_id}" : '-'),

            TextColumn::make('nopol')
                ->label('Nopol MT')
                ->badge()
                ->color('info')
                ->searchable(),

            TextColumn::make('capacity_kl')
                ->label('Kapasitas')
                ->formatStateUsing(fn($state) => $state ? $state . ' KL' : '-')
                ->sortable(),

            TextColumn::make('device.name')
                ->label('Device')
                ->badge()
                ->color('gray'),

            TextColumn::make('location')
                ->label('Lokasi (lat & long)')
                ->toggleable(isToggledHiddenByDefault: true)
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
                ->when($compNo === 4, fn(TextColumn $column) => $column->toggleable(isToggledHiddenByDefault: true))
                ->badge(fn(ScanLog $record) => $this->getScansForRecord($record)->has($compNo))
                ->getStateUsing(function (ScanLog $record) use ($compNo) {
                    $compLog = $this->getScansForRecord($record)->get($compNo);

                    return $compLog?->scanned_at
                        ? Carbon::parse($compLog->scanned_at)->format('H:i:s')
                        : '-';
                })
                ->color(fn(ScanLog $record) => $this->getScansForRecord($record)->has($compNo) ? 'success' : 'gray');

            $columns[] = TextColumn::make("isi_komp_{$compNo}")
                ->label("Isi Komp {$compNo}")
                ->when($compNo === 4, fn(TextColumn $column) => $column->toggleable(isToggledHiddenByDefault: true))
                ->badge(fn(ScanLog $record) => $this->getScansForRecord($record)->has($compNo))
                ->getStateUsing(function (ScanLog $record) use ($compNo) {
                    $compLog = $this->getScansForRecord($record)->get($compNo);

                    return $this->formatContentStatus($compLog?->content_status);
                })
                ->color(fn(ScanLog $record) => $this->contentStatusColor(
                    $this->getScansForRecord($record)->get($compNo)?->content_status
                ));
        }

        $columns[] = TextColumn::make('status')
            ->label('Status')
            ->badge()
            ->getStateUsing(function (ScanLog $record) {
                $scans = $this->getScansForRecord($record);
                $totalComps = TankerCompartment::where('tanker_id', $record->tanker_id)->count();
                $scannedCount = $scans->count();

                return ($totalComps > 0 && $scannedCount >= $totalComps) ? 'Complete' : 'Belum Lengkap';
            })
            ->color(fn(string $state): string => match ($state) {
                'Complete' => 'success',
                'Belum Lengkap' => 'warning',
                default => 'gray',
            });

        $columns[] = TextColumn::make('tindakan')
            ->label('Status MT')
            ->badge()
            ->getStateUsing(function (ScanLog $record) {
                if (! $this->recordNeedsAction($record)) {
                    return 'Ready';
                }

                // Sudah ditangani (TL) → status kembali Ready
                return $this->recordActionHandled($record) ? 'Ready' : 'Butuh Tindakan';
            })
            ->color(fn(string $state): string => match ($state) {
                'Butuh Tindakan' => 'danger',
                'Ready' => 'success',
                default => 'gray',
            })
            ->tooltip(function (ScanLog $record): ?string {
                if ($this->recordNeedsAction($record) && $this->recordActionHandled($record)) {
                    return $this->recordActionNote($record) ?: 'Tindakan sudah dilakukan';
                }

                return null;
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
            ->paginated([25, 50, 100, 'all'])
            ->query(function (): Builder {
                [$startDate, $endDate] = $this->getFilterDateRange();

                return ScanLog::query()
                    ->when($startDate, fn(Builder $query) => $query->where('scan_logs.scanned_at', '>=', $startDate))
                    ->when($endDate, fn(Builder $query) => $query->where('scan_logs.scanned_at', '<=', $endDate))
                    ->join('tanker_compartments', 'scan_logs.tanker_compartment_id', '=', 'tanker_compartments.id')
                    ->join('tankers', 'tanker_compartments.tanker_id', '=', 'tankers.id')
                    ->leftJoin('scan_sessions', 'scan_logs.scan_session_id', '=', 'scan_sessions.id')
                    ->select([
                        DB::raw('MAX(scan_logs.id) as id'),
                        'scan_logs.driver_id',
                        'scan_logs.scan_session_id',
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
                        DB::raw('MAX(scan_sessions.action_note) as action_note'),
                        DB::raw('MAX(scan_sessions.action_handled_at) as action_handled_at'),
                        DB::raw('MAX(scan_sessions.action_handled_by) as action_handled_by'),
                    ])
                    ->groupBy([
                        'scan_logs.driver_id',
                        'scan_logs.scan_session_id',
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
