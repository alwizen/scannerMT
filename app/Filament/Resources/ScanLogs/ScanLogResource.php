<?php

namespace App\Filament\Resources\ScanLogs;

use App\Filament\Resources\ScanLogs\Pages\ManageScanLogs;
use App\Models\ScanLog;
use App\Models\TankerCompartment;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
        $sessionKey = $record->scan_session_id ?? 'legacy';
        $key = "{$record->driver_id}_{$record->tanker_id}_{$record->scan_date}_{$sessionKey}";

        if (! isset(static::$scansCache[$key])) {
            $query = ScanLog::query()
                ->where('driver_id', $record->driver_id)
                ->whereDate('scanned_at', $record->scan_date)
                ->whereHas('tankerCompartment', fn ($q) => $q->where('tanker_id', $record->tanker_id));

            if ($record->scan_session_id) {
                $query->where('scan_session_id', $record->scan_session_id);
            } else {
                $query->whereNull('scan_session_id');
            }

            static::$scansCache[$key] = $query
                ->with(['tankerCompartment', 'parkingLocation'])
                ->get()
                ->keyBy(fn ($item) => $item->tankerCompartment?->compartment_no);
        }

        return static::$scansCache[$key];
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
                TextEntry::make('tankerCompartment.id')
                    ->label('Tanker compartment'),
                TextEntry::make('latitude')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('longitude')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('scan_status')
                    ->label('Status Scan')
                    ->formatStateUsing(fn($state) => $state === 'done' ? 'Done' : 'Belum Lengkap'),
                TextEntry::make('is_inside_geofence')
                    ->label('Geofence Lokasi')
                    ->badge()
                    ->formatStateUsing(fn($state, ScanLog $record) => $state
                        ? 'Di Dalam Area (' . ($record->parkingLocation?->name ?? 'Parkir MT') . ')'
                        : 'Di Luar Area Parkir')
                    ->color(fn($state) => $state ? 'success' : 'danger'),
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

                    return $record->is_inside_geofence ? 'Di Dalam Area' : 'Di Luar Area';
                }),
        ];

        for ($i = 1; $i <= $maxCompartments; $i++) {
            $compNo = $i;
            $columns[] = TextColumn::make("komp_{$compNo}")
                ->label("Komp {$compNo}")
                ->when($compNo === 4, fn (TextColumn $column) => $column->toggleable(isToggledHiddenByDefault: true))
                ->badge(fn (ScanLog $record) => static::getScansForRecord($record)->has($compNo))
                ->getStateUsing(function (ScanLog $record) use ($compNo) {
                    $compLog = static::getScansForRecord($record)->get($compNo);

                    return $compLog?->scanned_at
                        ? Carbon::parse($compLog->scanned_at)->format('H:i:s')
                        : '-';
                })
                ->color(fn (ScanLog $record) => static::getScansForRecord($record)->has($compNo) ? 'success' : 'gray');
        }

        $columns[] = TextColumn::make('status')
            ->label('Status')
            ->badge()
            ->getStateUsing(function (ScanLog $record) {
                $scans = static::getScansForRecord($record);
                $totalComps = TankerCompartment::where('tanker_id', $record->tanker_id)->count();

                return ($totalComps > 0 && $scans->count() >= $totalComps) ? 'Complete' : 'Belum Lengkap';
            })
            ->color(fn (string $state): string => match ($state) {
                'Complete' => 'success',
                'Belum Lengkap' => 'warning',
                default => 'gray',
            });

        $columns[] = TextColumn::make('last_update')
            ->label('Last Update')
            ->getStateUsing(fn (ScanLog $record) => $record->last_update
                ? Carbon::parse($record->last_update)->format('d M Y H:i:s')
                : '-');

        return $table
            ->columns($columns)
            ->query(function (): Builder {
                return ScanLog::query()
                    ->join('tanker_compartments', 'scan_logs.tanker_compartment_id', '=', 'tanker_compartments.id')
                    ->join('tankers', 'tanker_compartments.tanker_id', '=', 'tankers.id')
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
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
