<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\PurchaseStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('reference')->unique();

            $table->string('status')
                ->default(PurchaseStatus::Pending->value);

                $table->foreignId('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();
            
            $table->unsignedBigInteger('total_amount');

            $table->timestamp('purchased_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'purchased_at']);










        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
