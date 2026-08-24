<?php

namespace App\Providers;

use App\Contracts\PaymentProvider;
use App\Events\PurchaseCompleted;
use App\Listeners\ProcessPurchaseAchievements;
use App\Payments\LocalPaymentProvider;
use Illuminate\Support\Facades\Event;
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