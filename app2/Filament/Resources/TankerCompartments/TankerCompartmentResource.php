<?php

namespace App\Filament\Resources\TankerCompartments;

use App\Filament\Resources\TankerCompartments\Pages\ManageTankerCompartments;
use App\Models\TankerCompartment;
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
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TankerCompartmentResource extends Resource
{
    protected static ?string $model = TankerCompartment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tanker_id')
                    ->relationship('tanker', 'id')
                    ->required(),
                TextInput::make('compartment_no')
                    ->required()
                    ->numeric(),
                TextInput::make('capacity_kl')
                    ->required()
                    ->numeric(),
                TextInput::make('rfid_uid')
                    ->required(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tanker.id')
                    ->label('Tanker'),
                TextEntry::make('compartment_no')
                    ->numeric(),
                TextEntry::make('capacity_kl')
                    ->numeric(),
                TextEntry::make('rfid_uid'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn(TankerCompartment $record): bool => $record->trashed()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanker.nopol')
                    ->label('Nopol MT')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tanker.capacity_kl')
                    ->label('Kapasitas MT')
                    ->suffix(' KL')
                    ->sortable(),

                TextColumn::make('compartment_no')
                    ->label('Kompartemen')
                    ->formatStateUsing(fn($state) => 'Comp ' . $state)
                    ->sortable(),

                TextColumn::make('capacity_kl')
                    ->label('Kapasitas Comp')
                    ->suffix(' KL')
                    ->sortable(),

                TextColumn::make('tanker.status')
                    ->label('Status MT')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'available' => 'success',
                        'maintenance' => 'warning',
                        'afkir' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'available' => 'Ready',
                        'maintenance' => 'Maintenance',
                        'afkir' => 'Afkir',
                        default => $state,
                    }),

                TextColumn::make('rfid_uid')
                    ->label('RFID UID')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('UID disalin'),
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
            'index' => ManageTankerCompartments::route('/'),
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
