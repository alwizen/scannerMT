<?php

namespace App\Filament\Resources\ScanLogs;

use App\Filament\Resources\ScanLogs\Pages\ManageScanLogs;
use App\Models\ScanLog;
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
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class ScanLogResource extends Resource
{
    protected static ?string $model = ScanLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

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
                    ->formatStateUsing(fn($state) => $state === 'done' ? 'Done' : 'Kurang'),
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
    return $table
        ->groups([
            Group::make('tankerCompartment.tanker.nopol')
                ->label('Nopol MT')
                ->collapsible(),
        ])
        ->defaultGroup('tankerCompartment.tanker.nopol')
        ->columns([
            TextColumn::make('driver.name')
                ->searchable(),

            TextColumn::make('device.name')
                ->searchable(),

            TextColumn::make('tankerCompartment.tanker.nopol')
                ->label('Nopol MT')
                ->searchable()
                ->hidden(),

            TextColumn::make('tankerCompartment.compartment_no')
                ->label('Kompartemen')
                ->formatStateUsing(fn ($state) => 'Comp ' . $state)
                ->sortable(),

            TextColumn::make('latitude')
                ->numeric()
                ->sortable(),

            TextColumn::make('longitude')
                ->numeric()
                ->sortable(),

            TextColumn::make('scanned_at')
                ->label('Scanned at')
                ->dateTime('d M Y H:i:s')
                ->sortable(),

            TextColumn::make('scan_status')
                ->label('Status Scan')
                ->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'done' => 'Done',
                    'kurang' => 'Kurang',
                    default => $state,
                })
                ->color(fn (string $state): string => match ($state) {
                    'done' => 'success',
                    'kurang' => 'warning',
                    default => 'gray',
                }),

            TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            //
        ])
        ->recordActions([
            ViewAction::make(),
            EditAction::make(),
            DeleteAction::make(),
        ])
        ->toolbarActions([
            // BulkActionGroup::make([
            //     DeleteBulkAction::make(),
            // ]),
        ]);
}

    public static function getPages(): array
    {
        return [
            'index' => ManageScanLogs::route('/'),
        ];
    }
}
