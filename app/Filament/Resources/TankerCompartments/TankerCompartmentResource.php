<?php

namespace App\Filament\Resources\TankerCompartments;

use App\Filament\Resources\TankerCompartments\Pages\ManageTankerCompartments;
use App\Models\Tanker;
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
use Filament\Schemas\Components\Grid;
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
use Illuminate\Validation\Rule;

class TankerCompartmentResource extends Resource
{
    protected static ?string $model = TankerCompartment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $slug = 'tanker-compartments';
    protected static ?string $modelLabel = 'MT Compartment';
    protected static ?string $pluralModelLabel = 'MT Compartment';
    protected static ?string $navigationLabel = 'MT Compartment';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tanker_id')
                    ->relationship('tanker', 'nopol')
                    ->required()
                    ->live()
                    ->disabled(fn (?TankerCompartment $record) => $record !== null && $record->exists)
                    ->afterStateUpdated(function (callable $set, $state) {
                        $tanker = Tanker::find($state);
                        $set('tanker_capacity', $tanker?->capacity_kl);
                    }),

                TextInput::make('tanker_capacity')
                    ->label('Kapasitas MT')
                    ->suffix(' KL')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (TextInput $component, $state, ?TankerCompartment $record) {
                        if ($record && $record->tanker) {
                            $component->state($record->tanker->capacity_kl);
                        }
                    }),

                Grid::make(2)
                    ->schema([
                        TextInput::make('capacity_kl')
                            ->label('Kapasitas Comp 1')
                            ->required()
                            ->numeric()
                            ->suffix(' KL'),
                        TextInput::make('rfid_uid')
                            ->label('RFID Comp 1')
                            ->required()
                            ->unique(table: 'tanker_compartments', column: 'rfid_uid', ignoreRecord: true),
                    ]),

                Grid::make(2)
                    ->schema([
                        TextInput::make('comp2_capacity')
                            ->label('Kapasitas Comp 2')
                            ->numeric()
                            ->suffix(' KL')
                            ->requiredWith('comp2_rfid'),
                        TextInput::make('comp2_rfid')
                            ->label('RFID Comp 2')
                            ->different('rfid_uid')
                            ->requiredWith('comp2_capacity')
                            ->rules(function (?TankerCompartment $record) {
                                $rule = Rule::unique('tanker_compartments', 'rfid_uid');
                                if ($record && $record->tanker) {
                                    $comp2 = $record->tanker->compartments->firstWhere('compartment_no', 2);
                                    if ($comp2) {
                                        $rule = $rule->ignore($comp2->id);
                                    }
                                }
                                return [$rule];
                            })
                            ->afterStateHydrated(function (TextInput $component, $state, ?TankerCompartment $record) {
                                if ($record && $record->tanker) {
                                    $comp = $record->tanker->compartments->firstWhere('compartment_no', 2);
                                    // Make sure capacity field is hydrated too
                                    $capacityField = $component->getContainer()->getComponent('comp2_capacity');
                                    if ($capacityField) {
                                        $capacityField->state($comp?->capacity_kl);
                                    }
                                    $component->state($comp?->rfid_uid);
                                }
                            }),
                    ]),

                Grid::make(2)
                    ->schema([
                        TextInput::make('comp3_capacity')
                            ->label('Kapasitas Comp 3')
                            ->numeric()
                            ->suffix(' KL')
                            ->requiredWith('comp3_rfid'),
                        TextInput::make('comp3_rfid')
                            ->label('RFID Comp 3')
                            ->different('rfid_uid')
                            ->different('comp2_rfid')
                            ->requiredWith('comp3_capacity')
                            ->rules(function (?TankerCompartment $record) {
                                $rule = Rule::unique('tanker_compartments', 'rfid_uid');
                                if ($record && $record->tanker) {
                                    $comp3 = $record->tanker->compartments->firstWhere('compartment_no', 3);
                                    if ($comp3) {
                                        $rule = $rule->ignore($comp3->id);
                                    }
                                }
                                return [$rule];
                            })
                            ->afterStateHydrated(function (TextInput $component, $state, ?TankerCompartment $record) {
                                if ($record && $record->tanker) {
                                    $comp = $record->tanker->compartments->firstWhere('compartment_no', 3);
                                    // Make sure capacity field is hydrated too
                                    $capacityField = $component->getContainer()->getComponent('comp3_capacity');
                                    if ($capacityField) {
                                        $capacityField->state($comp?->capacity_kl);
                                    }
                                    $component->state($comp?->rfid_uid);
                                }
                            }),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tanker.nopol')
                    ->label('Nopol MT'),
                TextEntry::make('tanker.capacity_kl')
                    ->label('Kapasitas MT')
                    ->suffix(' KL'),
                
                TextEntry::make('capacity_kl')
                    ->label('Kapasitas Comp 1')
                    ->suffix(' KL'),
                TextEntry::make('rfid_uid')
                    ->label('RFID Comp 1'),

                TextEntry::make('comp2_capacity')
                    ->label('Kapasitas Comp 2')
                    ->suffix(' KL')
                    ->getStateUsing(fn(TankerCompartment $record) => 
                        optional($record->tanker->compartments->firstWhere('compartment_no', 2))->capacity_kl ?? '-'
                    ),
                TextEntry::make('comp2_rfid')
                    ->label('RFID Comp 2')
                    ->getStateUsing(fn(TankerCompartment $record) => 
                        optional($record->tanker->compartments->firstWhere('compartment_no', 2))->rfid_uid ?? '-'
                    ),

                TextEntry::make('comp3_capacity')
                    ->label('Kapasitas Comp 3')
                    ->suffix(' KL')
                    ->getStateUsing(fn(TankerCompartment $record) => 
                        optional($record->tanker->compartments->firstWhere('compartment_no', 3))->capacity_kl ?? '-'
                    ),
                TextEntry::make('comp3_rfid')
                    ->label('RFID Comp 3')
                    ->getStateUsing(fn(TankerCompartment $record) => 
                        optional($record->tanker->compartments->firstWhere('compartment_no', 3))->rfid_uid ?? '-'
                    ),
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

                TextColumn::make('rfid_uid')
                    ->label('RFID Comp 1')
                    ->searchable(),

                TextColumn::make('comp2_rfid')
                    ->label('RFID Comp 2')
                    ->getStateUsing(fn(TankerCompartment $record) => 
                        optional($record->tanker->compartments->firstWhere('compartment_no', 2))->rfid_uid ?? '-'
                    ),

                TextColumn::make('comp3_rfid')
                    ->label('RFID Comp 3')
                    ->getStateUsing(fn(TankerCompartment $record) => 
                        optional($record->tanker->compartments->firstWhere('compartment_no', 3))->rfid_uid ?? '-'
                    ),

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
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->after(function (TankerCompartment $record, array $data) {
                        self::saveOtherCompartments($record, $data);
                    }),
                DeleteAction::make()
                    ->after(function (TankerCompartment $record) {
                        TankerCompartment::where('tanker_id', $record->tanker_id)->delete();
                    }),
                ForceDeleteAction::make()
                    ->after(function (TankerCompartment $record) {
                        TankerCompartment::where('tanker_id', $record->tanker_id)->forceDelete();
                    }),
                RestoreAction::make()
                    ->after(function (TankerCompartment $record) {
                        TankerCompartment::where('tanker_id', $record->tanker_id)->restore();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(function (\Illuminate\Support\Collection $records) {
                            $records->each(fn (TankerCompartment $record) => 
                                TankerCompartment::where('tanker_id', $record->tanker_id)->delete()
                            );
                        }),
                    ForceDeleteBulkAction::make()
                        ->after(function (\Illuminate\Support\Collection $records) {
                            $records->each(fn (TankerCompartment $record) => 
                                TankerCompartment::where('tanker_id', $record->tanker_id)->forceDelete()
                            );
                        }),
                    RestoreBulkAction::make()
                        ->after(function (\Illuminate\Support\Collection $records) {
                            $records->each(fn (TankerCompartment $record) => 
                                TankerCompartment::where('tanker_id', $record->tanker_id)->restore()
                            );
                        }),
                ]),
            ]);
    }

    public static function saveOtherCompartments(TankerCompartment $record, array $data): void
    {
        // Compartment 2
        if (!empty($data['comp2_rfid'])) {
            $comp2 = TankerCompartment::withTrashed()
                ->where('tanker_id', $record->tanker_id)
                ->where('compartment_no', 2)
                ->first();
            
            if ($comp2) {
                $comp2->restore();
                $comp2->update([
                    'capacity_kl' => $data['comp2_capacity'] ?? 0,
                    'rfid_uid' => $data['comp2_rfid']
                ]);
            } else {
                TankerCompartment::create([
                    'tanker_id' => $record->tanker_id,
                    'compartment_no' => 2,
                    'capacity_kl' => $data['comp2_capacity'] ?? 0,
                    'rfid_uid' => $data['comp2_rfid']
                ]);
            }
        } else {
            TankerCompartment::where('tanker_id', $record->tanker_id)->where('compartment_no', 2)->delete();
        }

        // Compartment 3
        if (!empty($data['comp3_rfid'])) {
            $comp3 = TankerCompartment::withTrashed()
                ->where('tanker_id', $record->tanker_id)
                ->where('compartment_no', 3)
                ->first();
            
            if ($comp3) {
                $comp3->restore();
                $comp3->update([
                    'capacity_kl' => $data['comp3_capacity'] ?? 0,
                    'rfid_uid' => $data['comp3_rfid']
                ]);
            } else {
                TankerCompartment::create([
                    'tanker_id' => $record->tanker_id,
                    'compartment_no' => 3,
                    'capacity_kl' => $data['comp3_capacity'] ?? 0,
                    'rfid_uid' => $data['comp3_rfid']
                ]);
            }
        } else {
            TankerCompartment::where('tanker_id', $record->tanker_id)->where('compartment_no', 3)->delete();
        }
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['tanker.compartments'])
            ->where('compartment_no', 1);
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
