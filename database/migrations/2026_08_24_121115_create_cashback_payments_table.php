<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the cashback payments table used to track badge cashback transactions.
     */
    public function up(): void
    {
        Schema::create('cashback_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('badge_id')
                ->constrained()
                ->cascadeOnDelete();

            // Store monetary values in the currency's minor unit.
            $table->unsignedBigInteger('amount');

            // The reference is used to identify the payment with the provider.
            $table->string('reference')->unique();

            $table->string('status')->default('pending');

            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // A user should only receive cashback once for a particular badge.
            $table->unique(['user_id', 'badge_id']);

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Remove the cashback payments table when the migration is rolled back.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashback_payments');
    }
};