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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('status_id')->constrained('booking_statuses')->restrictOnDelete();
            $table->date('check_in_date')->index();
            $table->date('check_out_date')->index();
            $table->unsignedTinyInteger('adults_count')->default(1);
            $table->unsignedTinyInteger('children_count')->default(0);
            $table->decimal('room_price_at_booking', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
