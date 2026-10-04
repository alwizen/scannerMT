<?php

namespace App\Filament\Resources\ScanLogs;

use App\Filament\Resources\ScanLogs\Pages\ManageScanLogs;
use App\Models\Driver;
use App\Models\ScanLog;
use App\Models\ScanSession;
use App\Models\Tanker;
use App\Models\TankerCompartment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use pxlrbt\FilamentExcel\Actions\ExportBulkAction;

class ScanLogResource extends Resource
{
    protected static ?string $model = ScanLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $slug = 'scan-logs';

    protected static ?string $modelLabel = 'Riwayat Scan';

    protected static ?string $pluralModelLabel = 'Riwayat Scan';

    protected static ?string $navigationLabel = 'Riwayat Scan';

    protected static array $scansCache = [];

    protected static function getScansForRecord(ScanLog $record)
    {
        $tankerId = $record->tanker_id ?? $record->tankerCompartment?->tanker_id;
        $scanDate = $record->scan_date
            ?? $record->scanned_at?->toDateString()
            ?? now()->toDateString();

        $sessionKey = $record->scan_session_id ?? 'legacy';
        $key = "{$record->driver_id}_{$tankerId}_{$scanDate}_{$sessionKey}";

        if (! isset(static::$scansCache[$key])) {
            $query = ScanLog::query()
                ->where('driver_id', $record->driver_id)
                ->whereDate('scanned_at', $scanDate)
                ->whereHas('tankerCompartment', fn($q) => $q->where('tanker_id', $tankerId));

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

    protected static function formatContentStatus(?string $status): string
    {
        return match ($status) {
            'sisa_minyak' => 'Sisa Minyak',
            'kosong' => 'Kosong',
            'air' => 'Air',
            'lainnya' => 'Lainnya',
            default => '-',
        };
    }

    protected static function contentStatusColor(?string $status): string
    {
        return match ($status) {
            'air', 'sisa_minyak' => 'danger',
            'kosong' => 'success',
            default => 'gray',
        };
    }

    protected static function recordNeedsAction(ScanLog $record): bool
    {
        return static::getScansForRecord($record)
            ->contains(fn($scan) => in_array(
                $scan->content_status,
                ScanSession::ACTION_REQUIRED_CONTENT_STATUSES,
                true,
            ));
    }

    protected static function recordActionNote(ScanLog $record): ?string
    {
        if (filled($record->action_note)) {
            return $record->action_note;
        }

        if ($record->scan_session_id) {
            return ScanSession::whereKey($record->scan_session_id)->value('action_note');
        }

        return null;
    }

    protected static function recordActionHandled(ScanLog $record): bool
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('driver_id')
                    ->relationship('driver', 'name')
                    ->required(),
                Select::make('device_id')
                    ->relationship('device', 'name')
                    ->required(),
                Select::make('tanker_compartment_id')
                    ->relationship('tankerCompartment', 'id')
                    ->required(),
                Select::make('content_status')
                    ->label('Isi Kompartemen')
                    ->options([
                        'kosong' => 'Kosong',
                        'air' => 'Air',
                        'sisa_minyak' => 'Sisa Minyak',
                        'lainnya' => 'Lainnya',
                    ])
                    ->required(),
                TextInput::make('note')
                    ->label('Catatan')
                    ->maxLength(1000),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                DateTimePicker::make('scanned_at')
                    ->required(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('driver.name')
                    ->label('Driver'),
                TextEntry::make('device.name')
                    ->label('Device'),
                TextEntry::make('scan_session_id')
                    ->label('Session ID')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn($state) => $state ? "#{$state}" : '-')
                    ->placeholder('-'),
                TextEntry::make('tankerCompartment.id')
                    ->label('Tanker compartment'),
                TextEntry::make('content_status')
                    ->label('Isi Kompartemen')
                    ->formatStateUsing(fn($state) => static::formatContentStatus($state))
                    ->placeholder('-'),
                TextEntry::make('note')
                    ->label('Catatan')
                    ->placeholder('-'),
                TextEntry::make('latitude')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('longitude')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('detail_kompartemen')
                    ->label('Isi per Kompartemen')
                    ->listWithLineBreaks()
                    ->getStateUsing(function (ScanLog $record): array {
                        $scans = static::getScansForRecord($record)
                            ->sortBy(fn($scan) => $scan->tankerCompartment?->compartment_no ?? PHP_INT_MAX);

                        return $scans->map(function ($scan) {
                            $compNo = $scan->tankerCompartment?->compartment_no ?? '-';
                            $scannedAt = $scan->scanned_at
                                ? Carbon::parse($scan->scanned_at)->format('H:i:s')
                                : '-';
                            $content = static::formatContentStatus($scan->content_status);

                            return "Komp {$compNo} ({$scannedAt}): {$content}";
                        })->values()->all();
                    })
                    ->placeholder('-'),
                TextEntry::make('scan_status')
                    ->label('Status Scan')
                    ->formatStateUsing(fn($state) => $state === 'done' ? 'Done' : 'Belum Lengkap'),
                TextEntry::make('is_inside_geofence')
                    ->label('Geofence Lokasi')
                    ->badge()
                    ->color(fn($state) => $state ? 'success' : 'danger'),
                TextEntry::make('scanSession.action_note')
                    ->label('Catatan Tindakan')
                    ->placeholder('-'),
                TextEntry::make('scanSession.action_handled_at')
                    ->label('Ditangani Pada')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('scanned_at')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        $maxCompartments = max(4, TankerCompartment::max('compartment_no') ?? 4);
        $columns = [

            TextColumn::make('scan_date')
                ->label('Tanggal')
                ->date('d M Y')
                ->sortable(),

            TextColumn::make('driver.name')
                ->label('Nama AMT')
                ->searchable(),

            TextColumn::make('driver.role')
                ->label('Role/Jabatan')
                ->badge()
                ->formatStateUsing(fn($state) => match ($state) {
                    'driver' => 'AMT 1',
                    'helper' => 'AMT 2',
                    default => $state,
                }),

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
                ->icon(Heroicon::OutlinedMapPin)
                ->color('info')
                ->getStateUsing(function (ScanLog $record) {
                    if ($record->latitude && $record->longitude) {
                        return "{$record->latitude}, {$record->longitude}";
                    }

                    return '-';
                })
                ->url(function (ScanLog $record): ?string {
                    if ($record->latitude && $record->longitude) {
                        return sprintf(
                            'https://www.google.com/maps?q=%s,%s',
                            $record->latitude,
                            $record->longitude
                        );
                    }

                    return null;
                })
                ->openUrlInNewTab(),
        ];

        for ($i = 1; $i <= $maxCompartments; $i++) {
            $compNo = $i;
            $columns[] = TextColumn::make("komp_{$compNo}")
                ->label("Komp {$compNo}")
                ->when($compNo === 4, fn(TextColumn $column) => $column->toggleable(isToggledHiddenByDefault: true))
                ->badge(fn(ScanLog $record) => static::getScansForRecord($record)->has($compNo))
                ->getStateUsing(function (ScanLog $record) use ($compNo) {
                    $compLog = static::getScansForRecord($record)->get($compNo);

                    return $compLog?->scanned_at
                        ? Carbon::parse($compLog->scanned_at)->format('H:i:s')
                        : '-';
                })
                ->color(fn(ScanLog $record) => static::getScansForRecord($record)->has($compNo) ? 'success' : 'gray');

            $columns[] = TextColumn::make("isi_komp_{$compNo}")
                ->label("Isi Komp {$compNo}")
                ->when($compNo === 4, fn(TextColumn $column) => $column->toggleable(isToggledHiddenByDefault: true))
                ->badge(fn(ScanLog $record) => static::getScansForRecord($record)->has($compNo))
                ->getStateUsing(function (ScanLog $record) use ($compNo) {
                    $compLog = static::getScansForRecord($record)->get($compNo);

                    return static::formatContentStatus($compLog?->content_status);
                })
                ->color(fn(ScanLog $record) => static::contentStatusColor(
                    static::getScansForRecord($record)->get($compNo)?->content_status
                ));
        }

        $columns[] = TextColumn::make('status')
            ->label('Status')
            ->badge()
            ->getStateUsing(function (ScanLog $record) {
                $scans = static::getScansForRecord($record);
                $totalComps = TankerCompartment::where('tanker_id', $record->tanker_id)->count();

                return ($totalComps > 0 && $scans->count() >= $totalComps) ? 'Complete' : 'Belum Lengkap';
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
                if (! static::recordNeedsAction($record)) {
                    return 'Ready';
                }

                // Sudah ditangani (TL) → status kembali Ready
                return static::recordActionHandled($record) ? 'Ready' : 'Butuh Tindakan';
            })
            ->color(fn(string $state): string => match ($state) {
                'Butuh Tindakan' => 'danger',
                'Ready' => 'success',
                default => 'gray',
            })
            ->tooltip(function (ScanLog $record): ?string {
                if (static::recordNeedsAction($record) && static::recordActionHandled($record)) {
                    return static::recordActionNote($record) ?: 'Tindakan sudah dilakukan';
                }

                return null;
            });

        $columns[] = TextColumn::make('catatan_tindakan')
            ->label('Catatan Tindakan')
            ->toggleable(isToggledHiddenByDefault: true)
            ->getStateUsing(function (ScanLog $record): string {
                if (! static::recordNeedsAction($record) || ! static::recordActionHandled($record)) {
                    return '-';
                }

                return static::recordActionNote($record) ?: '-';
            });

        $columns[] = TextColumn::make('last_update')
            ->label('Last Update')
            ->getStateUsing(fn(ScanLog $record) => $record->last_update
                ? Carbon::parse($record->last_update)->format('d M Y H:i:s')
                : '-');

        return $table
            ->columns($columns)
            ->paginated([25, 50, 100,])
            ->query(function (): Builder {
                return ScanLog::query()
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
            ->filters([
                Filter::make('scanned_at')
                    ->label('Range Tanggal')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Dari tanggal'),
                        DatePicker::make('until')
                            ->label('Sampai tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('scan_logs.scanned_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('scan_logs.scanned_at', '<=', $date),
                            );
                    }),
                Filter::make('driver_id')
                    ->label('Nama AMT')
                    ->schema([
                        Select::make('value')
                            ->label('Driver')
                            ->options(fn(): array => Driver::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable(),
                    ])
                    ->query(fn(Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn(Builder $query, $driverId): Builder => $query->where('scan_logs.driver_id', $driverId),
                    )),
                Filter::make('tanker_id')
                    ->label('Mobil Tangki')
                    ->schema([
                        Select::make('value')
                            ->label('Tanker')
                            ->options(fn(): array => Tanker::query()
                                ->orderBy('nopol')
                                ->pluck('nopol', 'id')
                                ->all())
                            ->searchable(),
                    ])
                    ->query(fn(Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn(Builder $query, $tankerId): Builder => $query->where('tanker_compartments.tanker_id', $tankerId),
                    )),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('sudahDiTl')
                        ->label('Sudah Di TL')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->visible(function (ScanLog $record): bool {
                            return static::recordNeedsAction($record)
                                && ! static::recordActionHandled($record);
                        })
                        ->schema([
                            Textarea::make('action_note')
                                ->label('Catatan Tindakan')
                                ->placeholder('Contoh: Menguras kompartemen, membuang air, pengecekan ulang, dll.')
                                ->helperText('Tuliskan tindakan apa yang sudah dilakukan terhadap MT ini.')
                                ->required(),
                        ])
                        ->action(function (array $data, ScanLog $record): void {
                            $sessionId = $record->scan_session_id;

                            if (! $sessionId) {
                                Notification::make()
                                    ->title('Tidak dapat menyimpan tindakan')
                                    ->body('Data scan ini tidak memiliki sesi ritase.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            ScanSession::whereKey($sessionId)->update([
                                'action_note' => $data['action_note'] ?? null,
                                'action_handled_at' => now(),
                                'action_handled_by' => auth()->id(),
                            ]);

                            static::$scansCache = [];

                            Notification::make()
                                ->title('Tindakan tersimpan')
                                ->body('Status MT ditandai Sudah Di TL.')
                                ->success()
                                ->send();
                        }),
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->icon(Heroicon::OutlinedBars3),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageScanLogs::route('/'),
        ];
    }
}
