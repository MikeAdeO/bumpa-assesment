<?php

namespace App\Providers;

use App\Contracts\PaymentProvider;
use App\Payments\LocalPaymentProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register the application's services and their dependencies.
     */
    public function register(): void
    {
        $this->app->bind(
            PaymentProvider::class,
            LocalPaymentProvider::class
        );
    }

    /**
     * Bootstrap the application's services.
     */
    public function boot(): void
    {
        //
    }
}