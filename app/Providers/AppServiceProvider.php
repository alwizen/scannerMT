<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            fn (): string => Blade::render('
                <div style="text-align: center; width: 100%; display: block; margin-top: 1.5rem;" class="text-xs text-gray-400 dark:text-gray-500">
                    PT. Pertamina Patra Niaga &copy; Copyright 2026
                </div>
            '),
        );
    }
}
