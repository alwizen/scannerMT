<?php

namespace App\Filament\Resources\ScanSessions;

use App\Filament\Resources\ScanSessions\Pages\ListScanSessions;
use App\Filament\Resources\ScanSessions\Pages\ViewScanSession;
use App\Models\Driver;
use App\Models\ScanSession;
use App\Models\Tanker;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

class ScanSessionResource extends Resource
{
    protected static ?string $model = ScanSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $slug = 'history-tl';

    protected static ?string $modelLabel = 'Riwayat Tindak Lanjut';

    protected static ?string $pluralModelLabel = 'Riwayat Tindak Lanjut';

    protected static ?string $navigationLabel = 'Riwayat Tindak Lanjut';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = ScanSession::actionHandled()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('started_at')
                    ->label('Waktu Mulai')
                    ->dateTime(),
                TextEntry::make('completed_at')
                    ->label('Waktu Selesai')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('driver.name')
                    ->label('Driver')
                    ->placeholder('-'),
                TextEntry::make('driver.role')
                    ->label('Role')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'driver' => 'AMT 1',
                        'helper' => 'AMT 2',
                        default => $state,
                    })
                    ->placeholder('-'),
                TextEntry::make('tanker.nopol')
                    ->label('Nopol MT')
                    ->placeholder('-'),
                TextEntry::make('tanker.capacity_kl')
                    ->label('Kapasitas')
                    ->formatStateUsing(fn($state) => $state ? $state . ' KL' : '-')
                    ->placeholder('-'),
                TextEntry::make('device.name')
                    ->label('Device')
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->label('Status Ritase')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state === 'completed' ? 'Complete' : ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn($state) => $state === 'completed' ? 'success' : 'warning'),
                TextEntry::make('scan_status')
                    ->label('Status Scan')
                    ->state(function (ScanSession $record): string {
                        $total = $record->tanker?->compartments()->count() ?? 0;
                        $scanned = $record->scanLogs()->distinct()->count('tanker_compartment_id');

                        if ($total <= 0) {
                            return '-';
                        }

                        return $scanned >= $total ? 'Done' : "Kurang ({$scanned}/{$total})";
                    })
                    ->badge()
                    ->color(function (ScanSession $record): string {
                        $total = $record->tanker?->compartments()->count() ?? 0;
                        $scanned = $record->scanLogs()->distinct()->count('tanker_compartment_id');

                        return ($total > 0 && $scanned >= $total) ? 'success' : 'warning';
                    }),
                TextEntry::make('action_status')
                    ->label('Status TL')
                    ->state(fn(ScanSession $record): string => $record->isActionHandled() ? 'Sudah Di TL' : 'Belum Ditangani')
                    ->badge()
                    ->color(fn(ScanSession $record): string => $record->isActionHandled() ? 'info' : 'danger'),
                TextEntry::make('action_note')
                    ->label('Catatan Tindakan')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('handledBy.name')
                    ->label('Ditangani Oleh')
                    ->placeholder('-'),
                TextEntry::make('action_handled_at')
                    ->label('Waktu Ditangani')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('started_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y')
                    ->sortable(),
                TextColumn::make('driver.name')
                    ->label('Driver')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('driver.role')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'driver' => 'AMT 1',
                        'helper' => 'AMT 2',
                        default => $state,
                    }),
                TextColumn::make('tanker.nopol')
                    ->label('Nopol MT')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tanker.capacity_kl')
                    ->label('Kapasitas')
                    ->formatStateUsing(fn($state) => $state ? $state . ' KL' : '-')
                    ->sortable(),
                TextColumn::make('device.name')
                    ->label('Device')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label('Ritase')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state === 'completed' ? 'Complete' : ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn($state) => $state === 'completed' ? 'success' : 'warning')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('scan_status')
                    ->label('Scan')
                    ->state(function (ScanSession $record): string {
                        $total = $record->tanker?->compartments()->count() ?? 0;
                        $scanned = $record->scanLogs()->distinct()->count('tanker_compartment_id');

                        if ($total <= 0) {
                            return '-';
                        }

                        return "{$scanned}/{$total}";
                    })
                    ->badge()
                    ->color(function (ScanSession $record): string {
                        $total = $record->tanker?->compartments()->count() ?? 0;
                        $scanned = $record->scanLogs()->distinct()->count('tanker_compartment_id');

                        return ($total > 0 && $scanned >= $total) ? 'success' : 'warning';
                    }),
                TextColumn::make('action_status')
                    ->label('Status TL')
                    ->badge()
                    ->state(fn(ScanSession $record): string => $record->isActionHandled() ? 'Sudah Di TL' : 'Belum Ditangani')
                    ->color(fn(ScanSession $record): string => $record->isActionHandled() ? 'info' : 'danger'),
                TextColumn::make('action_handled_at')
                    ->label('Waktu Ditangani')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('action_note')
                    ->label('Catatan Tindakan')
                    ->limit(40)
                    ->tooltip(fn(ScanSession $record): ?string => $record->action_note)
                    ->placeholder('-'),

                TextColumn::make('handledBy.name')
                    ->label('Ditangani Oleh')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('started_at')
                    ->label('Range Tanggal Ritase')
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
                                fn(Builder $query, $date): Builder => $query->whereDate('started_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('started_at', '<=', $date),
                            );
                    }),
                Filter::make('action_status')
                    ->label('Status TL')
                    ->schema([
                        Select::make('value')
                            ->label('Status')
                            ->options([
                                'handled' => 'Sudah Di TL',
                                'pending' => 'Belum Ditangani',
                            ])
                            ->placeholder('Semua'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'handled' => $query->actionHandled(),
                            'pending' => $query->actionPending(),
                            default => $query,
                        };
                    }),
                Filter::make('driver_id')
                    ->label('Driver')
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
                        fn(Builder $query, $driverId): Builder => $query->where('driver_id', $driverId),
                    )),
                Filter::make('tanker_id')
                    ->label('Nopol MT')
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
                        fn(Builder $query, $tankerId): Builder => $query->where('tanker_id', $tankerId),
                    )),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('action_handled_at', 'desc')
            ->modifyQueryUsing(function (Builder $query): Builder {
                // Riwayat Tindak Lanjut: tampilkan semua session, fokus pada yang sudah/belum di TL
                return $query->with([
                    'driver:id,name,role',
                    'device:id,name',
                    'tanker:id,nopol,capacity_kl',
                    'handledBy:id,name',
                    'scanLogs:id,scan_session_id,tanker_compartment_id,content_status',
                ]);
            });
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScanSessions::route('/'),
            'view' => ViewScanSession::route('/{record}'),
        ];
    }
}
