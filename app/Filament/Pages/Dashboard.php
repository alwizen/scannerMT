<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;

class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    protected static ?string $title = 'Dashboard';

   protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->schema([
                    Select::make('period')
                        ->label('Periode')
                        ->options([
                            'today' => 'Hari ini',
                            'yesterday' => 'Kemarin',
                            '7_days' => '7 hari terakhir',
                            '1_month' => '1 bulan terakhir',
                            'custom' => 'Custom range',
                        ])
                        ->default('today')
                        ->live()
                        ->required(),
                    DatePicker::make('startDate')
                        ->label('Tanggal mulai')
                        ->visible(fn (callable $get): bool => $get('period') === 'custom')
                        ->required(fn (callable $get): bool => $get('period') === 'custom'),
                    DatePicker::make('endDate')
                        ->label('Tanggal akhir')
                        ->visible(fn (callable $get): bool => $get('period') === 'custom')
                        ->required(fn (callable $get): bool => $get('period') === 'custom')
                        ->after('startDate'),
                ]),
        ];
    }

}