<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cashback payout has to land somewhere. These columns live directly on
 * `users` (not a separate `payment_accounts` table) because there's exactly
 * one payout destination per user in this assessment — a one-to-many table
 * would model a relationship that doesn't exist here. All four are
 * nullable: a user with no bank details on file simply can't be paid out
 * (PaystackPaymentProvider logs and returns false instead of throwing),
 * which matters for LocalPaymentProvider too since these fields are always
 * present on CashbackPayout regardless of which provider is bound.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('bank_account_number')->nullable()->after('remember_token');
            $table->string('bank_code')->nullable()->after('bank_account_number');
            $table->string('bank_account_name')->nullable()->after('bank_code');

            // Paystack requires a "transfer recipient" to exist before it will
            // send a transfer. Caching the recipient_code here means a user's
            // second, third, ... badge unlock reuses the same recipient
            // instead of registering a new one with Paystack on every payout.
            $table->string('paystack_recipient_code')->nullable()->after('bank_account_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'bank_account_number',
                'bank_code',
                'bank_account_name',
                'paystack_recipient_code',
            ]);
        });
    }
};
