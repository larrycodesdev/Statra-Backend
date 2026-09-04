<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deviation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('signal_type', 20);
            $table->float('reading_value');
            $table->float('z_score');
            $table->string('quality_flag', 20)->default('good');
            $table->dateTime('scored_at');
            $table->unsignedBigInteger('composite_score_id')->nullable();
            $table->foreign('composite_score_id')->references('id')->on('composite_deviation_scores')->nullOnDelete();

            $table->index(['patient_id', 'scored_at'], 'idx_deviation_scores_patient_scored');
            $table->index('composite_score_id', 'idx_deviation_scores_composite');
        });

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("ALTER TABLE deviation_scores ADD CONSTRAINT chk_deviation_signal_type
                CHECK (signal_type IN ('temperature','spo2','heart_rate','hrv','steps'))");
            DB::statement("ALTER TABLE deviation_scores ADD CONSTRAINT chk_deviation_quality_flag
                CHECK (quality_flag IN ('good','low_confidence','motion_artifact'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deviation_scores');
    }
};
