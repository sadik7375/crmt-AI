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
        Schema::create('medicine_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->onDelete('set null');
            $table->string('name');
            $table->string('time');
            $table->enum('status', ['taken', 'missed'])->default('missed');
            $table->date('log_date');
            $table->timestamp('taken_at')->nullable();
            $table->timestamps();

            // Add composite unique key to prevent duplicate logs for the same medicine on the same day
            $table->unique(['user_id', 'medicine_id', 'log_date'], 'user_med_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicine_logs');
    }
};
