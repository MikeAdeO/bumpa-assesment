<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();

            $table->unsignedInteger('required_achievements');

            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index([
                'is_active',
                'required_achievements',
            ]);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
