<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement("ALTER TABLE composite_deviation_scores ADD CONSTRAINT composite_status_check
            CHECK (status IN ('stable','watch','elevated','urgent'))");
        DB::statement("ALTER TABLE composite_deviation_scores ADD CONSTRAINT composite_confidence_check
            CHECK (confidence IN ('low','medium','high'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement("ALTER TABLE composite_deviation_scores DROP CONSTRAINT IF EXISTS composite_status_check");
        DB::statement("ALTER TABLE composite_deviation_scores DROP CONSTRAINT IF EXISTS composite_confidence_check");
    }
};