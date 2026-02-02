<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Observers\PaymentObserver; // Hakikisha umei-import Observer yako
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
        \App\Models\Payment::observe(\App\Observers\PaymentObserver::class);
    }
}
