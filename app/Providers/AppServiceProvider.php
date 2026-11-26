<?php

namespace App\Providers;

use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
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
        if (request()->header('X-Forwarded-Proto') === 'https' ||
            request()->server('HTTP_X_FORWARDED_PROTO') === 'https' ||
            str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Register Pusher
        if (! app()->runningInConsole()) {
            // Register Pusher & Vite Assets
            FilamentAsset::register([
                Js::make('jurnal-realtime', asset('js/app/jurnal-realtime.js')),

                // Baris ini yang sebelumnya menyebabkan error saat artisan command dijalankan
                Js::make('app-js', Vite::asset('resources/js/app.js')),
            ]);
        }
    }
}
