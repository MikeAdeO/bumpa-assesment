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
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('achievement_group_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->string('rule_type');

            $table->unsignedInteger('threshold');

            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['achievement_group_id', 'name']);

            $table->index([
                'achievement_group_id',
                'rule_type',
                'threshold',
            ]);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
