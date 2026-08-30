<?php

namespace App\Filament\Resources\TankerCompartments;

use App\Filament\Resources\TankerCompartments\Pages\ManageTankerCompartments;
use App\Models\Tanker;
use App\Models\TankerCompartment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TankerCompartmentResource extends Resource
{
    protected static ?string $model = TankerCompartment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $slug = 'tanker-compartments';
    protected static ?string $modelLabel = 'MT Compartment';
    protected static ?string $pluralModelLabel = 'MT Compartment';
    protected static ?string $navigationLabel = 'MT Compartment';

    public static function getCompartmentDefaults(int $totalCapacity): array
    {
        if ($totalCapacity <= 8) {
            return [$totalCapacity];
        } elseif ($totalCapacity <= 16) {
            return [8, $totalCapacity - 8];
        } else {
            return [8, 8, $totalCapacity - 16];
        }
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tanker_id')
                    ->relationship(
                        name: 'tanker',
                        titleAttribute: 'nopol',
                        modifyQueryUsing: fn (Builder $query, ?TankerCompartment $record) => 
                            $query->whereDoesntHave('compartments', function (Builder $q) use ($record) {
                                if ($record?->tanker_id) {
                                    $q->where('tanker_id', '!=', $record->tanker_id);
                                }
                            })
                    )
                    ->required()
                    ->live()
                    ->disabled(fn (?TankerCompartment $record) => $record !== null && $record->exists)
                    ->afterStateUpdated(function (callable $set, $state) {
                        $tanker = Tanker::find($state);
                        $capacity = (int) ($tanker?->capacity_kl ?? 0);
                        $set('tanker_capacity', $capacity);

                        if ($capacity > 0) {
                            $defaults = self::getCompartmentDefaults($capacity);

                            // Comp 1
                            $set('capacity_kl', $defaults[0] ?? $capacity);

                            // Comp 2
                            if (isset($defaults[1])) {
                                $set('comp2_capacity', $defaults[1]);
                            } else {
                                $set('comp2_capacity', null);
                                $set('comp2_rfid', null);
                            }

                            // Comp 3
                            if (isset($defaults[2])) {
                                $set('comp3_capacity', $defaults[2]);
                            } else {
                                $set('comp3_capacity', null);
                                $set('comp3_rfid', null);
                            }
                        }
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

                // COMPARTMENT 1
                Hidden::make('capacity_kl')
                    ->default(fn (callable $get) => self::getCompartmentDefaults((int) $get('tanker_capacity'))[0] ?? 0),

                Grid::make(2)
                    ->schema([
                        Select::make('type')
                            ->label('Tipe Comp 1')
                            ->options([
                                'rfid' => 'RFID',
                                'qrcode' => 'QR Code',
                            ])
                            ->default('rfid')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set, $state, callable $get) {
                                if ($state === 'qrcode' && empty($get('rfid_uid'))) {
                                    $set('rfid_uid', 'QR-C1-' . strtoupper(Str::random(8)));
                                }
                            }),

                        TextInput::make('rfid_uid')
                            ->label(fn (callable $get) => $get('type') === 'qrcode' ? 'Kode QR Comp 1' : 'RFID Comp 1')
                            ->required()
                            ->unique(table: 'tanker_compartments', column: 'rfid_uid', ignoreRecord: true)
                            ->suffixAction(
                                Action::make('generate_qr_1')
                                    ->icon('heroicon-o-qr-code')
                                    ->tooltip('Generate Kode QR')
                                    ->visible(fn (callable $get) => $get('type') === 'qrcode')
                                    ->action(function (callable $set) {
                                        $set('rfid_uid', 'QR-C1-' . strtoupper(Str::random(8)));
                                    })
                            ),
                    ]),

                Placeholder::make('qr_preview_1')
                    ->label('Pratinjau QR Code Comp 1')
                    ->visible(fn (callable $get) => $get('type') === 'qrcode' && !empty($get('rfid_uid')))
                    ->content(function (callable $get) {
                        $code = $get('rfid_uid');
                        if (empty($code)) return '';
                        $dataUrl = (new \chillerlan\QRCode\QRCode())->render($code);
                        return new HtmlString('<div class="p-2 bg-white rounded shadow-sm border dark:bg-gray-800 flex items-center justify-center" style="width: 110px; height: 110px;"><img src="' . $dataUrl . '" style="width: 90px; height: 90px; object-fit: contain;" /></div>');
                    }),

                // COMPARTMENT 2 (Only visible if MT capacity > 8 KL)
                Hidden::make('comp2_capacity'),

                Grid::make(2)
                    ->visible(fn (callable $get) => ((int) $get('tanker_capacity')) > 8)
                    ->schema([
                        Select::make('comp2_type')
                            ->label('Tipe Comp 2')
                            ->options([
                                'rfid' => 'RFID',
                                'qrcode' => 'QR Code',
                            ])
                            ->default('rfid')
                            ->live()
                            ->afterStateUpdated(function (callable $set, $state, callable $get) {
                                if ($state === 'qrcode' && empty($get('comp2_rfid'))) {
                                    $set('comp2_rfid', 'QR-C2-' . strtoupper(Str::random(8)));
                                }
                            })
                            ->afterStateHydrated(function (Select $component, $state, ?TankerCompartment $record) {
                                if ($record && $record->tanker) {
                                    $comp = $record->tanker->compartments->firstWhere('compartment_no', 2);
                                    if ($comp) {
                                        $component->state($comp->type ?? 'rfid');
                                    }
                                }
                            }),

                        TextInput::make('comp2_rfid')
                            ->label(fn (callable $get) => $get('comp2_type') === 'qrcode' ? 'Kode QR Comp 2' : 'RFID Comp 2')
                            ->different('rfid_uid')
                            ->required(fn (callable $get) => ((int) $get('tanker_capacity')) > 8)
                            ->suffixAction(
                                Action::make('generate_qr_2')
                                    ->icon('heroicon-o-qr-code')
                                    ->tooltip('Generate Kode QR')
                                    ->visible(fn (callable $get) => $get('comp2_type') === 'qrcode')
                                    ->action(function (callable $set) {
                                        $set('comp2_rfid', 'QR-C2-' . strtoupper(Str::random(8)));
                                    })
                            )
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
                                    $capacityField = $component->getContainer()->getComponent('comp2_capacity');
                                    if ($capacityField) {
                                        $capacityField->state($comp?->capacity_kl);
                                    }
                                    $component->state($comp?->rfid_uid);
                                }
                            }),
                    ]),

                Placeholder::make('qr_preview_2')
                    ->label('Pratinjau QR Code Comp 2')
                    ->visible(fn (callable $get) => ((int) $get('tanker_capacity')) > 8 && $get('comp2_type') === 'qrcode' && !empty($get('comp2_rfid')))
                    ->content(function (callable $get) {
                        $code = $get('comp2_rfid');
                        if (empty($code)) return '';
                        $dataUrl = (new \chillerlan\QRCode\QRCode())->render($code);
                        return new HtmlString('<div class="p-2 bg-white rounded shadow-sm border dark:bg-gray-800 flex items-center justify-center" style="width: 110px; height: 110px;"><img src="' . $dataUrl . '" style="width: 90px; height: 90px; object-fit: contain;" /></div>');
                    }),

                // COMPARTMENT 3 (Only visible if MT capacity >= 24 KL)
                Hidden::make('comp3_capacity'),

                Grid::make(2)
                    ->visible(fn (callable $get) => ((int) $get('tanker_capacity')) >= 24)
                    ->schema([
                        Select::make('comp3_type')
                            ->label('Tipe Comp 3')
                            ->options([
                                'rfid' => 'RFID',
                                'qrcode' => 'QR Code',
                            ])
                            ->default('rfid')
                            ->live()
                            ->afterStateUpdated(function (callable $set, $state, callable $get) {
                                if ($state === 'qrcode' && empty($get('comp3_rfid'))) {
                                    $set('comp3_rfid', 'QR-C3-' . strtoupper(Str::random(8)));
                                }
                            })
                            ->afterStateHydrated(function (Select $component, $state, ?TankerCompartment $record) {
                                if ($record && $record->tanker) {
                                    $comp = $record->tanker->compartments->firstWhere('compartment_no', 3);
                                    if ($comp) {
                                        $component->state($comp->type ?? 'rfid');
                                    }
                                }
                            }),

                        TextInput::make('comp3_rfid')
                            ->label(fn (callable $get) => $get('comp3_type') === 'qrcode' ? 'Kode QR Comp 3' : 'RFID Comp 3')
                            ->different('rfid_uid')
                            ->different('comp2_rfid')
                            ->required(fn (callable $get) => ((int) $get('tanker_capacity')) >= 24)
                            ->suffixAction(
                                Action::make('generate_qr_3')
                                    ->icon('heroicon-o-qr-code')
                                    ->tooltip('Generate Kode QR')
                                    ->visible(fn (callable $get) => $get('comp3_type') === 'qrcode')
                                    ->action(function (callable $set) {
                                        $set('comp3_rfid', 'QR-C3-' . strtoupper(Str::random(8)));
                                    })
                            )
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
                                    $capacityField = $component->getContainer()->getComponent('comp3_capacity');
                                    if ($capacityField) {
                                        $capacityField->state($comp?->capacity_kl);
                                    }
                                    $component->state($comp?->rfid_uid);
                                }
                            }),
                    ]),

                Placeholder::make('qr_preview_3')
                    ->label('Pratinjau QR Code Comp 3')
                    ->visible(fn (callable $get) => ((int) $get('tanker_capacity')) >= 24 && $get('comp3_type') === 'qrcode' && !empty($get('comp3_rfid')))
                    ->content(function (callable $get) {
                        $code = $get('comp3_rfid');
                        if (empty($code)) return '';
                        $dataUrl = (new \chillerlan\QRCode\QRCode())->render($code);
                        return new HtmlString('<div class="p-2 bg-white rounded shadow-sm border dark:bg-gray-800 flex items-center justify-center" style="width: 110px; height: 110px;"><img src="' . $dataUrl . '" style="width: 90px; height: 90px; object-fit: contain;" /></div>');
                    }),
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

                TextEntry::make('type')
                    ->label('Tipe Comp 1')
                    ->badge()
                    ->color(fn ($state) => $state === 'qrcode' ? 'info' : 'primary')
                    ->formatStateUsing(fn ($state) => strtoupper($state ?? 'rfid')),
                TextEntry::make('capacity_kl')
                    ->label('Kapasitas Comp 1')
                    ->suffix(' KL'),
                TextEntry::make('rfid_uid')
                    ->label('RFID/QR Comp 1'),

                TextEntry::make('comp2_type')
                    ->label('Tipe Comp 2')
                    ->visible(fn (TankerCompartment $record) => ($record->tanker?->capacity_kl ?? 0) > 8)
                    ->badge()
                    ->color(fn ($state) => $state === 'qrcode' ? 'info' : 'primary')
                    ->getStateUsing(fn (TankerCompartment $record) => 
                        strtoupper(optional($record->tanker->compartments->firstWhere('compartment_no', 2))->type ?? 'rfid')
                    ),
                TextEntry::make('comp2_capacity')
                    ->label('Kapasitas Comp 2')
                    ->visible(fn (TankerCompartment $record) => ($record->tanker?->capacity_kl ?? 0) > 8)
                    ->suffix(' KL')
                    ->getStateUsing(fn (TankerCompartment $record) => 
                        optional($record->tanker->compartments->firstWhere('compartment_no', 2))->capacity_kl ?? '-'
                    ),
                TextEntry::make('comp2_rfid')
                    ->label('RFID/QR Comp 2')
                    ->visible(fn (TankerCompartment $record) => ($record->tanker?->capacity_kl ?? 0) > 8)
                    ->getStateUsing(fn (TankerCompartment $record) => 
                        optional($record->tanker->compartments->firstWhere('compartment_no', 2))->rfid_uid ?? '-'
                    ),

                TextEntry::make('comp3_type')
                    ->label('Tipe Comp 3')
                    ->visible(fn (TankerCompartment $record) => ($record->tanker?->capacity_kl ?? 0) >= 24)
                    ->badge()
                    ->color(fn ($state) => $state === 'qrcode' ? 'info' : 'primary')
                    ->getStateUsing(fn (TankerCompartment $record) => 
                        strtoupper(optional($record->tanker->compartments->firstWhere('compartment_no', 3))->type ?? 'rfid')
                    ),
                TextEntry::make('comp3_capacity')
                    ->label('Kapasitas Comp 3')
                    ->visible(fn (TankerCompartment $record) => ($record->tanker?->capacity_kl ?? 0) >= 24)
                    ->suffix(' KL')
                    ->getStateUsing(fn (TankerCompartment $record) => 
                        optional($record->tanker->compartments->firstWhere('compartment_no', 3))->capacity_kl ?? '-'
                    ),
                TextEntry::make('comp3_rfid')
                    ->label('RFID/QR Comp 3')
                    ->visible(fn (TankerCompartment $record) => ($record->tanker?->capacity_kl ?? 0) >= 24)
                    ->getStateUsing(fn (TankerCompartment $record) => 
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
                    ->label('Comp 1')
                    ->searchable()
                    ->description(fn (TankerCompartment $record) => 'Type: ' . strtoupper($record->type ?? 'rfid')),

                TextColumn::make('comp2_rfid')
                    ->label('Comp 2')
                    ->getStateUsing(fn (TankerCompartment $record) => 
                        optional($record->tanker->compartments->firstWhere('compartment_no', 2))->rfid_uid ?? '-'
                    )
                    ->description(function (TankerCompartment $record) {
                        $comp2 = $record->tanker->compartments->firstWhere('compartment_no', 2);
                        return $comp2 ? 'Type: ' . strtoupper($comp2->type ?? 'rfid') : null;
                    }),

                TextColumn::make('comp3_rfid')
                    ->label('Comp 3')
                    ->getStateUsing(fn (TankerCompartment $record) => 
                        optional($record->tanker->compartments->firstWhere('compartment_no', 3))->rfid_uid ?? '-'
                    )
                    ->description(function (TankerCompartment $record) {
                        $comp3 = $record->tanker->compartments->firstWhere('compartment_no', 3);
                        return $comp3 ? 'Type: ' . strtoupper($comp3->type ?? 'rfid') : null;
                    }),

                TextColumn::make('tanker.status')
                    ->label('Status MT')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'available' => 'success',
                        'maintenance' => 'warning',
                        'afkir' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
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
                Action::make('download_qr')
                    ->label('QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->modalHeading(fn (TankerCompartment $record) => 'QR Code - ' . ($record->tanker?->nopol ?? 'MT'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(function (TankerCompartment $record) {
                        $compartments = $record->tanker?->compartments ?? collect([$record]);
                        return view('filament.components.qr-code-modal', [
                            'compartments' => $compartments,
                        ]);
                    }),
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
        $capacity = (int) ($record->tanker?->capacity_kl ?? 0);
        $defaults = self::getCompartmentDefaults($capacity);

        // Ensure Comp 1 capacity is updated if needed
        if (isset($defaults[0]) && $record->capacity_kl != $defaults[0]) {
            $record->update(['capacity_kl' => $defaults[0]]);
        }

        // Compartment 2 (Only if MT capacity > 8 KL)
        if ($capacity > 8 && !empty($data['comp2_rfid'])) {
            $comp2 = TankerCompartment::withTrashed()
                ->where('tanker_id', $record->tanker_id)
                ->where('compartment_no', 2)
                ->first();
            
            $comp2Cap = $data['comp2_capacity'] ?? ($defaults[1] ?? 8);

            if ($comp2) {
                $comp2->restore();
                $comp2->update([
                    'type' => $data['comp2_type'] ?? 'rfid',
                    'capacity_kl' => $comp2Cap,
                    'rfid_uid' => $data['comp2_rfid']
                ]);
            } else {
                TankerCompartment::create([
                    'tanker_id' => $record->tanker_id,
                    'compartment_no' => 2,
                    'type' => $data['comp2_type'] ?? 'rfid',
                    'capacity_kl' => $comp2Cap,
                    'rfid_uid' => $data['comp2_rfid']
                ]);
            }
        } else {
            TankerCompartment::where('tanker_id', $record->tanker_id)->where('compartment_no', 2)->delete();
        }

        // Compartment 3 (Only if MT capacity >= 24 KL)
        if ($capacity >= 24 && !empty($data['comp3_rfid'])) {
            $comp3 = TankerCompartment::withTrashed()
                ->where('tanker_id', $record->tanker_id)
                ->where('compartment_no', 3)
                ->first();
            
            $comp3Cap = $data['comp3_capacity'] ?? ($defaults[2] ?? 8);

            if ($comp3) {
                $comp3->restore();
                $comp3->update([
                    'type' => $data['comp3_type'] ?? 'rfid',
                    'capacity_kl' => $comp3Cap,
                    'rfid_uid' => $data['comp3_rfid']
                ]);
            } else {
                TankerCompartment::create([
                    'tanker_id' => $record->tanker_id,
                    'compartment_no' => 3,
                    'type' => $data['comp3_type'] ?? 'rfid',
                    'capacity_kl' => $comp3Cap,
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
