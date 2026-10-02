<?php

namespace App\Filament\Custom;

use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Jacobtims\FilamentLogger\Resources\ActivityResource as BaseActivityResource;

class ActivityLogResource extends BaseActivityResource
{
    protected static string | \UnitEnum | null $navigationGroup = 'Lain-lain';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('log_name')
                    ->label('Tipe')
                    ->badge()
                    ->colors(static::getLogNameColors())
                    ->formatStateUsing(fn ($state) => ucwords($state))
                    ->sortable(),

                TextColumn::make('event')
                    ->label('Event')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->wrap(),

                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(function ($state, Model $record) {
                        if (! $state) {
                            return '-';
                        }

                        return Str::of($state)->afterLast('\\')->headline().' # '.$record->subject_id;
                    }),

                TextColumn::make('causer.name')
                    ->label('User'),

                TextColumn::make('created_at')
                    ->label('Logged At')
                    ->dateTime(config('filament-logger.datetime_format', 'd/m/Y H:i:s'), config('app.timezone'))
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Tipe')
                    ->options(static::getLogNameList()),

                SelectFilter::make('subject_type')
                    ->label('Subject Type')
                    ->options(static::getSubjectTypeList()),

                Filter::make('properties->old')
                    ->indicateUsing(function (array $data): ?string {
                        if (empty($data['old'] ?? null)) {
                            return null;
                        }

                        return 'Old Attributes: '.$data['old'];
                    })
                    ->schema([
                        TextInput::make('old')
                            ->label('Old')
                            ->hint('Search in old attributes JSON'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['old'] ?? null)) {
                            return $query;
                        }

                        return $query->where('properties->old', 'like', "%{$data['old']}%");
                    }),

                Filter::make('properties->attributes')
                    ->indicateUsing(function (array $data): ?string {
                        if (empty($data['new'] ?? null)) {
                            return null;
                        }

                        return 'New Attributes: '.$data['new'];
                    })
                    ->schema([
                        TextInput::make('new')
                            ->label('New')
                            ->hint('Search in new attributes JSON'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['new'] ?? null)) {
                            return $query;
                        }

                        return $query->where('properties->attributes', 'like', "%{$data['new']}%");
                    }),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('logged_at')
                            ->label('Logged At')
                            ->displayFormat(config('filament-logger.date_format', 'd/m/Y')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['logged_at'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', $date),
                            );
                    }),
            ]);
    }

    protected static function getSubjectTypeList(): array
    {
        if (config('filament-logger.resources.enabled', true)) {
            $subjects = [];
            $exceptResources = [...config('filament-logger.resources.exclude'), config('filament-logger.activity_resource')];
            $removedExcludedResources = collect(Filament::getResources())->filter(function ($resource) use ($exceptResources) {
                return ! in_array($resource, $exceptResources);
            });
            foreach ($removedExcludedResources as $resource) {
                $model = $resource::getModel();
                $subjects[$model] = Str::of(class_basename($model))->headline();
            }

            return $subjects;
        }

        return [];
    }

    protected static function getLogNameColors(): array
    {
        $customs = [];

        foreach (config('filament-logger.custom') ?? [] as $custom) {
            if (filled($custom['color'] ?? null)) {
                $customs[$custom['color']] = $custom['log_name'];
            }
        }

        return array_merge(
            (config('filament-logger.resources.enabled') && config('filament-logger.resources.color')) ? [
                config('filament-logger.resources.color') => config('filament-logger.resources.log_name'),
            ] : [],
            (config('filament-logger.models.enabled') && config('filament-logger.models.color')) ? [
                config('filament-logger.models.color') => config('filament-logger.models.log_name'),
            ] : [],
            (config('filament-logger.access.enabled') && config('filament-logger.access.color')) ? [
                config('filament-logger.access.color') => config('filament-logger.access.log_name'),
            ] : [],
            (config('filament-logger.notifications.enabled') && config('filament-logger.notifications.color')) ? [
                config('filament-logger.notifications.color') => config('filament-logger.notifications.log_name'),
            ] : [],
            $customs,
        );
    }

    protected static function getLogNameList(): array
    {
        $customs = [];

        foreach (config('filament-logger.custom') ?? [] as $custom) {
            $customs[$custom['log_name']] = $custom['log_name'];
        }

        return array_merge(
            config('filament-logger.resources.enabled') ? [
                config('filament-logger.resources.log_name') => config('filament-logger.resources.log_name'),
            ] : [],
            config('filament-logger.models.enabled') ? [
                config('filament-logger.models.log_name') => config('filament-logger.models.log_name'),
            ] : [],
            config('filament-logger.access.enabled')
                ? [config('filament-logger.access.log_name') => config('filament-logger.access.log_name')]
                : [],
            config('filament-logger.notifications.enabled') ? [
                config('filament-logger.notifications.log_name') => config('filament-logger.notifications.log_name'),
            ] : [],
            $customs,
        );
    }
}
