<?php

namespace App\Filament\Resources\Drivers;

use App\Filament\Resources\Drivers\Pages\ManageDrivers;
use App\Models\Driver;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static string | UnitEnum | null $navigationGroup = 'Master Data';

    protected static ?string $modelLabel = 'AMT';

    protected static ?string $pluralModelLabel = 'AMT';

    protected static ?string $navigationLabel = 'AMT';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('driver_no')
                    ->label('Nomor AMT')
                    ->required(),
                TextInput::make('name')
                    ->label('Nama AMT')
                    ->required(),
                TextInput::make('phone')
                    ->label('Nomor Telepon'),
                Select::make('role')
                    ->label('Jabatan')
                    ->options([
                        'driver' => 'AMT 1',
                        'helper' => 'AMT 2',
                    ])
                    ->required(),
                Toggle::make('is_active')
                    ->label('Status')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('driver_no')
                    ->label('ID AMT'),
                TextEntry::make('name')
                    ->label('Nama AMT'),
                TextEntry::make('phone')
                    ->placeholder('-'),
                TextEntry::make('role'),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn(Driver $record): bool => $record->trashed()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('driver_no')
                    ->label('ID AMT')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable()
                    ->label('Nama AMT'),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('role')
                    ->searchable()
                    ->label('Jabatan')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'driver' => 'AMT 1',
                        'helper' => 'AMT 2',
                        default => $state,
                    }),

                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDrivers::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
