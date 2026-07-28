<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('doctor_name');
            $table->string('specialty');
            $table->string('clinic');
            $table->dateTime('date_time');
            $table->text('reason')->nullable();
            $table->enum('reminder_alert', ['1Day', '2Hours', '30Mins', 'None'])->default('1Day');
            $table->boolean('is_past')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
