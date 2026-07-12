<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE composite_deviation_scores (
                id                   BIGINT IDENTITY(1,1) PRIMARY KEY,
                patient_id           BIGINT NOT NULL,
                computed_at          DATETIME NOT NULL,
                temp_z               FLOAT NULL,
                spo2_z               FLOAT NULL,
                hr_z                 FLOAT NULL,
                hrv_z                FLOAT NULL,
                activity_z           FLOAT NULL,
                temp_contribution    FLOAT NULL,
                spo2_contribution    FLOAT NULL,
                hr_contribution      FLOAT NULL,
                hrv_contribution     FLOAT NULL,
                activity_contribution FLOAT NULL,
                total_score          FLOAT NOT NULL DEFAULT 0,
                status               VARCHAR(10) NOT NULL DEFAULT 'stable',
                confidence           VARCHAR(10) NOT NULL DEFAULT 'low',
                temperature_absolute FLOAT NULL,
                outreach_recommended BIT NOT NULL DEFAULT 0,
                outreach_reason      VARCHAR(500) NULL,
                CONSTRAINT composite_status_check
                    CHECK (status IN ('stable','watch','elevated','urgent')),
                CONSTRAINT composite_confidence_check
                    CHECK (confidence IN ('low','medium','high')),
                CONSTRAINT composite_patient_id_foreign
                    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
            )
        ");

        DB::statement("CREATE INDEX composite_scores_patient_computed_idx ON composite_deviation_scores (patient_id, computed_at)");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS composite_deviation_scores");
    }
};
