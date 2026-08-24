<?php

namespace App\Providers;

use App\Contracts\PaymentProvider;
use App\Payments\LocalPaymentProvider;
use App\Payments\PaystackPaymentProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register the application's services and their dependencies.
     *
     * PaymentProvider resolves to PaystackPaymentProvider when a Paystack
     * secret key is configured, and to the simulated LocalPaymentProvider
     * otherwise. This is the only place that decision is made — nothing in
     * the achievement/badge/cashback chain (ProcessCashback, PaymentService)
     * knows or cares which one it got.
     */
    public function register(): void
    {
        $this->app->bind(PaymentProvider::class, function (): PaymentProvider {
            $secret = config('services.paystack.secret');

            if (! $secret) {
                return new LocalPaymentProvider;
            }

            return new PaystackPaymentProvider(
                $secret,
                config('services.paystack.base_url')
            );
        });
    }

    /**
     * Bootstrap the application's services.
     */
    public function boot(): void
    {
        //
    }
}
