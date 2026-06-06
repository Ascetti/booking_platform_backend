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
        Schema::create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('rate_plans')->nullOnDelete();
            $table->integer('modifier_percent')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('meal_plan', 10)->default('RO');
            $table->unsignedInteger('cancellation_free_days')->default(2);
            $table->unsignedInteger('cancellation_penalty_percent')->default(10);
            $table->unsignedInteger('prepayment_percent')->default(0);
            $table->unsignedInteger('min_days_before_arrival')->nullable();
            $table->unsignedInteger('max_days_before_arrival')->nullable();
            $table->unsignedInteger('min_stay_days')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_plans');
    }
};
