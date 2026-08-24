<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Payment provider identifier, e.g. paystack, flutterwave.
            $table->string('provider');

            // Generic identifier returned by the payment provider.
            // For Paystack this can be the recipient_code.
            $table->string('account_reference');

            $table->string('account_name');
            $table->string('account_number');

            // Provider-specific data that should not leak into the domain model.
            $table->json('metadata')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique([
                'provider',
                'account_reference',
            ]);

            $table->index([
                'user_id',
                'provider',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
