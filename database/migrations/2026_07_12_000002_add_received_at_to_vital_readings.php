<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // received_at = server clock at ingestion time; recorded_at = device clock
        // The gap between them reveals sync lag (e.g. device offline for hours)
        DB::statement("ALTER TABLE vital_readings ADD received_at DATETIME NULL");

        // Backfill existing rows from created_at (best approximation)
        DB::statement("UPDATE vital_readings SET received_at = created_at WHERE received_at IS NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE vital_readings DROP COLUMN received_at");
    }
};
