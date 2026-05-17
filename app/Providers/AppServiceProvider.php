<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Throwable;

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
        date_default_timezone_set(config('app.timezone', 'Europe/Budapest'));

        try {
            if (config('database.default') === 'mysql') {
                DB::statement("SET time_zone = '" . Carbon::now(config('app.timezone'))->format('P') . "'");
            }
        } catch (Throwable) {
            //
        }
    }
}
