<?php

namespace App\Filament\Resources\ParkingLocations;

use App\Filament\Resources\ParkingLocations\Pages\CreateParkingLocation;
use App\Filament\Resources\ParkingLocations\Pages\EditParkingLocation;
use App\Filament\Resources\ParkingLocations\Pages\ListParkingLocations;
use App\Models\ParkingLocation;
use BackedEnum;
use EduardoRibeiroDev\FilamentLeaflet\Enums\TileLayer;
use EduardoRibeiroDev\FilamentLeaflet\Fields\MapPicker;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Shapes\Circle;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Shapes\Polygon;
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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ParkingLocationResource extends Resource
{
    protected static ?string $model = ParkingLocation::class;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Lokasi Parkir MT';

    protected static ?string $pluralModelLabel = 'Lokasi Parkir MT';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Lokasi Parkir MT')
                    ->components([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Lokasi Parkir')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('code')
                                    ->label('Kode Lokasi')
                                    ->maxLength(100),
                                Select::make('type')
                                    ->label('Tipe Geofence')
                                    ->options([
                                        'polygon' => 'Polygon Area (Bentuk Titik Poligon)',
                                        'radius' => 'Radius Circle (Lingkaran Radius)',
                                    ])
                                    ->required()
                                    ->default('polygon')
                                    ->live(),
                                TextInput::make('radius_meters')
                                    ->label('Radius Geofence (Meter)')
                                    ->numeric()
                                    ->default(100)
                                    ->visible(fn ($get) => $get('type') === 'radius'),
                                TextInput::make('latitude')
                                    ->label('Latitude (Pusat)')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('longitude')
                                    ->label('Longitude (Pusat)')
                                    ->numeric()
                                    ->required(),
                                Toggle::make('is_active')
                                    ->label('Status Aktif')
                                    ->default(true)
                                    ->required(),
                            ]),
                    ]),

                Section::make('Marking Geofence Pada Peta Leaflet')
                    ->description('Tentukan area geofence lokasi parkir MT secara visual menggunakan peta Leaflet interaktif.')
                    ->components([
                        View::make('filament.forms.components.leaflet-map-picker'),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Nama Lokasi'),
                TextEntry::make('code')
                    ->label('Kode Lokasi')
                    ->placeholder('-'),
                TextEntry::make('type')
                    ->label('Tipe Geofence')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'polygon' => 'success',
                        'radius' => 'info',
                        default => 'gray',
                    }),
                TextEntry::make('latitude')
                    ->label('Latitude Pusat'),
                TextEntry::make('longitude')
                    ->label('Longitude Pusat'),
                TextEntry::make('radius_meters')
                    ->label('Radius')
                    ->formatStateUsing(fn ($state, ParkingLocation $record) => $record->type === 'radius' ? $state . ' meter' : '-'),
                IconEntry::make('is_active')
                    ->label('Status Aktif')
                    ->boolean(),
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
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Lokasi')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('type')
                    ->label('Tipe Geofence')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'polygon' => 'Polygon',
                        'radius' => 'Radius',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'polygon' => 'success',
                        'radius' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('latitude')
                    ->label('Latitude')
                    ->numeric(),

                TextColumn::make('longitude')
                    ->label('Longitude')
                    ->numeric(),

                TextColumn::make('radius_meters')
                    ->label('Radius (Meter)')
                    ->formatStateUsing(fn ($state, ParkingLocation $record) => $record->type === 'radius' ? $state . ' m' : '-'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
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
            'index' => ListParkingLocations::route('/'),
            'create' => CreateParkingLocation::route('/create'),
            'edit' => EditParkingLocation::route('/{record}/edit'),
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
