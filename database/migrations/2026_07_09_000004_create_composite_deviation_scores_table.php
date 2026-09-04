<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('composite_deviation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->dateTime('computed_at');
            $table->float('temp_z')->nullable();
            $table->float('spo2_z')->nullable();
            $table->float('hr_z')->nullable();
            $table->float('hrv_z')->nullable();
            $table->float('activity_z')->nullable();
            $table->float('temp_contribution')->nullable();
            $table->float('spo2_contribution')->nullable();
            $table->float('hr_contribution')->nullable();
            $table->float('hrv_contribution')->nullable();
            $table->float('activity_contribution')->nullable();
            $table->float('total_score')->default(0);
            $table->string('status', 10)->default('stable');
            $table->string('confidence', 10)->default('low');
            $table->float('temperature_absolute')->nullable();
            $table->boolean('outreach_recommended')->default(false);
            $table->string('outreach_reason', 500)->nullable();

            $table->index(['patient_id', 'computed_at'], 'composite_scores_patient_computed_idx');
        });

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("ALTER TABLE composite_deviation_scores ADD CONSTRAINT composite_status_check
                CHECK (status IN ('stable','watch','elevated','urgent'))");
            DB::statement("ALTER TABLE composite_deviation_scores ADD CONSTRAINT composite_confidence_check
                CHECK (confidence IN ('low','medium','high'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('composite_deviation_scores');
    }
};
