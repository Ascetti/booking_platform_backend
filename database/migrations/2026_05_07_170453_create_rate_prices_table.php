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
        Schema::create('rate_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_category_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('price', 12, 2);
            $table->unique(['rate_plan_id', 'room_category_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_prices');
    }
};
