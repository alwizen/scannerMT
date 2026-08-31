<?php

namespace App\Filament\Widgets;

use App\Models\ScanLog;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ScanMTTable extends TableWidget
{
    protected static ?string $heading = 'Realtime Monitoring Pretrip Mainhole';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->poll(3)
            ->paginated([25, 50, 100])
            ->query(fn(): Builder => ScanLog::query())
            ->columns([
                TextColumn::make('driver.name'),
                TextColumn::make('device.name'),
                TextColumn::make('tankerCompartment.compartment_no')
                    ->label('Kompartemen')
                    ->formatStateUsing(fn($state) => 'Comp ' . $state),
                TextColumn::make('latitude')
                    ->numeric(),
                TextColumn::make('longitude')
                    ->numeric(),
                TextColumn::make('scanned_at')
                    ->dateTime(),
                TextColumn::make('scan_status')
                    ->label('Status Scan')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'done' => 'Done',
                        'kurang' => 'Kurang',
                        default => $state,
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'done' => 'success',
                        'kurang' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('is_inside_geofence')
                    ->label('Geofence Lokasi')
                    ->badge()
                    ->formatStateUsing(fn ($state, ScanLog $record) => $state
                        ? 'Di Dalam (' . ($record->parkingLocation?->name ?? 'Parkir MT') . ')'
                        : 'Di Luar Area')
                    ->color(fn ($state) => $state ? 'success' : 'danger'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->label('Terakhir Update'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
