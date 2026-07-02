<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
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
        // Shared hosting (mis. InfinityFree) membatasi panjang key index ke
        // 1000 byte; utf8mb4 × 255 char = 1020 byte. Batasi default ke 191.
        Schema::defaultStringLength(191);

        // Use our vanilla-CSS pagination markup everywhere.
        Paginator::defaultView('pagination.lajur');

        // Localise dates (month names in charts, etc.).
        Carbon::setLocale('id');
    }
}
