<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('prescription_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('dosage');
            $table->string('time');
            $table->boolean('taken')->default(false);
            $table->string('doctor_name')->default('Manual Entry');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
