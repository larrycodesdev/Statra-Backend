<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE patient_baselines (
                id               BIGINT IDENTITY(1,1) PRIMARY KEY,
                patient_id       BIGINT NOT NULL,
                signal_type      VARCHAR(20) NOT NULL,
                activity_context VARCHAR(20) NOT NULL DEFAULT 'any',
                rolling_mean     FLOAT NOT NULL DEFAULT 0,
                rolling_variance FLOAT NOT NULL DEFAULT 0,
                rolling_stddev   FLOAT NOT NULL DEFAULT 0,
                sample_count     INT NOT NULL DEFAULT 0,
                window_days      INT NOT NULL DEFAULT 28,
                baseline_confidence VARCHAR(10) NOT NULL DEFAULT 'low',
                last_updated_at  DATETIME NULL,
                CONSTRAINT patient_baselines_signal_type_check
                    CHECK (signal_type IN ('heart_rate','spo2','temperature','hrv','steps')),
                CONSTRAINT patient_baselines_activity_context_check
                    CHECK (activity_context IN ('resting','active','sleep','any')),
                CONSTRAINT patient_baselines_confidence_check
                    CHECK (baseline_confidence IN ('low','medium','high')),
                CONSTRAINT patient_baselines_unique
                    UNIQUE (patient_id, signal_type, activity_context),
                CONSTRAINT patient_baselines_patient_id_foreign
                    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
            )
        ");

        DB::statement("CREATE INDEX patient_baselines_patient_signal_idx ON patient_baselines (patient_id, signal_type)");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS patient_baselines");
    }
};
