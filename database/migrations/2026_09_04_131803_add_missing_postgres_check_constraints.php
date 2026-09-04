<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        // These were all wrapped in an sqlsrv-only branch and skipped on PostgreSQL.
        DB::statement("ALTER TABLE patient_baselines ADD CONSTRAINT patient_baselines_signal_type_check
            CHECK (signal_type IN ('heart_rate','spo2','temperature','hrv','steps'))");
        DB::statement("ALTER TABLE patient_baselines ADD CONSTRAINT patient_baselines_activity_context_check
            CHECK (activity_context IN ('resting','active','sleep','any'))");
        DB::statement("ALTER TABLE patient_baselines ADD CONSTRAINT patient_baselines_confidence_check
            CHECK (baseline_confidence IN ('low','medium','high'))");

        DB::statement("ALTER TABLE deviation_scores ADD CONSTRAINT chk_deviation_signal_type
            CHECK (signal_type IN ('temperature','spo2','heart_rate','hrv','steps'))");
        DB::statement("ALTER TABLE deviation_scores ADD CONSTRAINT chk_deviation_quality_flag
            CHECK (quality_flag IN ('good','low_confidence','motion_artifact'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement("ALTER TABLE patient_baselines DROP CONSTRAINT IF EXISTS patient_baselines_signal_type_check");
        DB::statement("ALTER TABLE patient_baselines DROP CONSTRAINT IF EXISTS patient_baselines_activity_context_check");
        DB::statement("ALTER TABLE patient_baselines DROP CONSTRAINT IF EXISTS patient_baselines_confidence_check");
        DB::statement("ALTER TABLE deviation_scores DROP CONSTRAINT IF EXISTS chk_deviation_signal_type");
        DB::statement("ALTER TABLE deviation_scores DROP CONSTRAINT IF EXISTS chk_deviation_quality_flag");
    }
};