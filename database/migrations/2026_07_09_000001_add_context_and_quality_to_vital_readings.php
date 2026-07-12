<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE vital_readings ADD activity_context VARCHAR(20) NULL");
        DB::statement("ALTER TABLE vital_readings ADD quality_flag VARCHAR(20) NOT NULL DEFAULT 'good'");

        DB::statement("
            ALTER TABLE vital_readings ADD CONSTRAINT vital_readings_activity_context_check
            CHECK (activity_context IN ('resting','light','active','sleep','unknown'))
        ");

        DB::statement("
            ALTER TABLE vital_readings ADD CONSTRAINT vital_readings_quality_flag_check
            CHECK (quality_flag IN ('good','low_confidence','motion_artifact'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            DECLARE @sql NVARCHAR(512)
            SELECT @sql = 'ALTER TABLE vital_readings DROP CONSTRAINT [' + name + ']'
            FROM sys.check_constraints
            WHERE parent_object_id = OBJECT_ID('vital_readings') AND name = 'vital_readings_activity_context_check'
            IF @sql IS NOT NULL EXEC(@sql)
        ");
        DB::statement("
            DECLARE @sql NVARCHAR(512)
            SELECT @sql = 'ALTER TABLE vital_readings DROP CONSTRAINT [' + name + ']'
            FROM sys.check_constraints
            WHERE parent_object_id = OBJECT_ID('vital_readings') AND name = 'vital_readings_quality_flag_check'
            IF @sql IS NOT NULL EXEC(@sql)
        ");
        DB::statement("ALTER TABLE vital_readings DROP COLUMN activity_context");
        DB::statement("ALTER TABLE vital_readings DROP COLUMN quality_flag");
    }
};
