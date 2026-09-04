<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('signal_type', 20);
            $table->string('activity_context', 20)->default('any');
            $table->float('rolling_mean')->default(0);
            $table->float('rolling_variance')->default(0);
            $table->float('rolling_stddev')->default(0);
            $table->integer('sample_count')->default(0);
            $table->integer('window_days')->default(28);
            $table->string('baseline_confidence', 10)->default('low');
            $table->dateTime('last_updated_at')->nullable();

            $table->unique(['patient_id', 'signal_type', 'activity_context'], 'patient_baselines_unique');
            $table->index(['patient_id', 'signal_type'], 'patient_baselines_patient_signal_idx');
        });

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("ALTER TABLE patient_baselines ADD CONSTRAINT patient_baselines_signal_type_check
                CHECK (signal_type IN ('heart_rate','spo2','temperature','hrv','steps'))");
            DB::statement("ALTER TABLE patient_baselines ADD CONSTRAINT patient_baselines_activity_context_check
                CHECK (activity_context IN ('resting','active','sleep','any'))");
            DB::statement("ALTER TABLE patient_baselines ADD CONSTRAINT patient_baselines_confidence_check
                CHECK (baseline_confidence IN ('low','medium','high'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_baselines');
    }
};
