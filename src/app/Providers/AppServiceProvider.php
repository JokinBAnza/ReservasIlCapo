<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // En producción todos los enlaces generados deben ser https
        // (incluidos los enlaces firmados de anulación de los emails)
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
