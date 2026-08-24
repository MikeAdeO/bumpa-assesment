<?php

namespace Tests\Unit;

use App\Contracts\PaymentProvider;
use App\Models\User;
use App\Payments\Data\CashbackPayout;
use App\Payments\LocalPaymentProvider;
use App\Payments\PaystackPaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackPaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_recipient_then_sends_a_transfer(): void
    {
        Http::fake([
            'https://api.paystack.test/transferrecipient' => Http::response([
                'status' => true,
                'data' => ['recipient_code' => 'RCP_test123'],
            ]),
            'https://api.paystack.test/transfer' => Http::response([
                'status' => true,
                'data' => ['transfer_code' => 'TRF_test456'],
            ]),
        ]);

        $user = User::factory()->create([
            'bank_account_number' => '0123456789',
            'bank_code' => '044',
            'bank_account_name' => 'Jane Doe',
            'paystack_recipient_code' => null,
        ]);

        $provider = new PaystackPaymentProvider('sk_test_secret', 'https://api.paystack.test');

        $successful = $provider->sendCashback(new CashbackPayout(
            userId: $user->id,
            amount: 30000,
            currency: 'NGN',
            reference: 'cashback-1-1',
            accountNumber: $user->bank_account_number,
            bankCode: $user->bank_code,
            accountName: $user->bank_account_name,
        ));

        $this->assertTrue($successful);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.test/transferrecipient'
            && $request['account_number'] === '0123456789'
            && $request['bank_code'] === '044'
            && $request->hasHeader('Authorization', 'Bearer sk_test_secret'));

        Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.test/transfer'
            && $request['recipient'] === 'RCP_test123'
            && $request['amount'] === 30000
            && $request['reference'] === 'cashback-1-1');

        $this->assertSame('RCP_test123', $user->fresh()->paystack_recipient_code);
    }

    public function test_it_reuses_a_cached_recipient_code_without_recreating_it(): void
    {
        Http::fake([
            'https://api.paystack.test/transfer' => Http::response([
                'status' => true,
                'data' => ['transfer_code' => 'TRF_test456'],
            ]),
        ]);

        $user = User::factory()->create([
            'bank_account_number' => '0123456789',
            'bank_code' => '044',
            'paystack_recipient_code' => 'RCP_already_cached',
        ]);

        $provider = new PaystackPaymentProvider('sk_test_secret', 'https://api.paystack.test');

        $successful = $provider->sendCashback(new CashbackPayout(
            userId: $user->id,
            amount: 30000,
            currency: 'NGN',
            reference: 'cashback-1-1',
            accountNumber: $user->bank_account_number,
            bankCode: $user->bank_code,
        ));

        $this->assertTrue($successful);

        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.paystack.test/transferrecipient');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.test/transfer'
            && $request['recipient'] === 'RCP_already_cached');
    }

    public function test_it_fails_gracefully_when_the_transfer_request_is_rejected(): void
    {
        Http::fake([
            'https://api.paystack.test/transfer' => Http::response([
                'status' => false,
                'message' => 'Insufficient balance',
            ], 400),
        ]);

        $user = User::factory()->create([
            'bank_account_number' => '0123456789',
            'bank_code' => '044',
            'paystack_recipient_code' => 'RCP_already_cached',
        ]);

        $provider = new PaystackPaymentProvider('sk_test_secret', 'https://api.paystack.test');

        $successful = $provider->sendCashback(new CashbackPayout(
            userId: $user->id,
            amount: 30000,
            currency: 'NGN',
            reference: 'cashback-1-1',
            accountNumber: $user->bank_account_number,
            bankCode: $user->bank_code,
        ));

        $this->assertFalse($successful);
    }

    public function test_it_fails_gracefully_when_the_user_has_no_bank_account_on_file(): void
    {
        Http::fake();

        $user = User::factory()->create([
            'bank_account_number' => null,
            'bank_code' => null,
        ]);

        $provider = new PaystackPaymentProvider('sk_test_secret', 'https://api.paystack.test');

        $successful = $provider->sendCashback(new CashbackPayout(
            userId: $user->id,
            amount: 30000,
            currency: 'NGN',
            reference: 'cashback-1-1',
        ));

        $this->assertFalse($successful);

        Http::assertNothingSent();
    }

    /**
     * AppServiceProvider is what actually chooses between the two providers
     * based on config — this proves that wiring, not just each provider in
     * isolation.
     */
    public function test_local_provider_is_bound_when_no_paystack_secret_is_configured(): void
    {
        config(['services.paystack.secret' => null]);

        $this->app->forgetInstance(PaymentProvider::class);

        $this->assertInstanceOf(LocalPaymentProvider::class, app(PaymentProvider::class));
    }

    public function test_paystack_provider_is_bound_when_a_paystack_secret_is_configured(): void
    {
        config(['services.paystack.secret' => 'sk_test_secret']);

        $this->app->forgetInstance(PaymentProvider::class);

        $this->assertInstanceOf(PaystackPaymentProvider::class, app(PaymentProvider::class));
    }
}
