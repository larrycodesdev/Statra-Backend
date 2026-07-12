<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE patients ADD calibration_status VARCHAR(20) NOT NULL DEFAULT 'calibrating'");
        DB::statement("ALTER TABLE patients ADD calibration_start_at DATETIME NULL");

        DB::statement("
            ALTER TABLE patients ADD CONSTRAINT patients_calibration_status_check
            CHECK (calibration_status IN ('calibrating','active','stale'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            DECLARE @sql NVARCHAR(512)
            SELECT @sql = 'ALTER TABLE patients DROP CONSTRAINT [' + name + ']'
            FROM sys.check_constraints
            WHERE parent_object_id = OBJECT_ID('patients') AND name = 'patients_calibration_status_check'
            IF @sql IS NOT NULL EXEC(@sql)
        ");
        DB::statement("ALTER TABLE patients DROP COLUMN calibration_status");
        DB::statement("ALTER TABLE patients DROP COLUMN calibration_start_at");
    }
};
