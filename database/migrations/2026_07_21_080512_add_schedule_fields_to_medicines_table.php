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
        Schema::table('medicines', function (Blueprint $table) {
            $table->text('raw_notes')->nullable()->after('doctor_name');
            $table->date('start_date')->nullable()->after('raw_notes');
            $table->integer('duration_days')->nullable()->after('start_date');
            $table->string('meal_relation', 50)->nullable()->after('duration_days');
            $table->decimal('dose_quantity', 4, 2)->nullable()->after('meal_relation');
            $table->string('dose_unit', 50)->nullable()->after('dose_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn([
                'raw_notes',
                'start_date',
                'duration_days',
                'meal_relation',
                'dose_quantity',
                'dose_unit'
            ]);
        });
    }
};
