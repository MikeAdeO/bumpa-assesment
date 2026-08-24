<?php

namespace App\Providers;

use App\Events\PurchaseCompleted;
use App\Listeners\ProcessPurchaseAchievements;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(
            PurchaseCompleted::class,
            ProcessPurchaseAchievements::class,
        );
    }
}