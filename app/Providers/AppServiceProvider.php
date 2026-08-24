<?php

namespace App\Providers;

use App\Contracts\PaymentProvider;
use App\Events\PurchaseCompleted;
use App\Listeners\ProcessPurchaseAchievements;
use App\Services\FakePaymentProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services and bind interfaces to their implementations.
     */
    public function register(): void
    {
        $this->app->singleton(
            PaymentProvider::class,
            FakePaymentProvider::class
        );
    }

    /**
     * Register the application's event listeners.
     */
    public function boot(): void
    {
        Event::listen(
            PurchaseCompleted::class,
            ProcessPurchaseAchievements::class,
        );
    }
}