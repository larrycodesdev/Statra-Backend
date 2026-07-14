<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE deviation_scores (
                id                 BIGINT IDENTITY(1,1) PRIMARY KEY,
                patient_id         BIGINT        NOT NULL,
                signal_type        VARCHAR(20)   NOT NULL,
                reading_value      FLOAT         NOT NULL,
                z_score            FLOAT         NOT NULL,
                quality_flag       VARCHAR(20)   NOT NULL DEFAULT 'good',
                scored_at          DATETIME      NOT NULL,
                composite_score_id BIGINT        NULL,

                CONSTRAINT fk_deviation_scores_patient
                    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE NO ACTION,

                CONSTRAINT fk_deviation_scores_composite
                    FOREIGN KEY (composite_score_id) REFERENCES composite_deviation_scores(id) ON DELETE NO ACTION,

                CONSTRAINT chk_deviation_signal_type
                    CHECK (signal_type IN ('temperature','spo2','heart_rate','hrv','steps')),

                CONSTRAINT chk_deviation_quality_flag
                    CHECK (quality_flag IN ('good','low_confidence','motion_artifact'))
            )
        ");

        DB::statement("
            CREATE INDEX idx_deviation_scores_patient_scored
                ON deviation_scores (patient_id, scored_at)
        ");

        DB::statement("
            CREATE INDEX idx_deviation_scores_composite
                ON deviation_scores (composite_score_id)
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS deviation_scores");
    }
};
