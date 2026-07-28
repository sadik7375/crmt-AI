<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('lab_name')->nullable();
            $table->string('report_type');
            $table->date('report_date');
            $table->enum('status', ['Normal', 'High', 'Low', 'Pending'])->default('Normal');
            $table->string('image_path')->nullable();
            $table->longText('ocr_text')->nullable();
            $table->json('parameters')->nullable(); // Dynamic test parameters array
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_reports');
    }
};
