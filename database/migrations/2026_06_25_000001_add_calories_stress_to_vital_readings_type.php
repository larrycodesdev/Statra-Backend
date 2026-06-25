<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the existing type check constraint (Azure SQL Server)
        DB::statement("
            DECLARE @sql NVARCHAR(512)
            SELECT @sql = 'ALTER TABLE vital_readings DROP CONSTRAINT [' + name + ']'
            FROM sys.check_constraints
            WHERE parent_object_id = OBJECT_ID('vital_readings') AND name LIKE '%type%'
            IF @sql IS NOT NULL EXEC(@sql)
        ");

        DB::statement("
            ALTER TABLE vital_readings ADD CONSTRAINT vital_readings_type_check
            CHECK ([type] IN ('heart_rate','spo2','temperature','blood_pressure','steps','sleep_state','hrv','calories','stress'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            DECLARE @sql NVARCHAR(512)
            SELECT @sql = 'ALTER TABLE vital_readings DROP CONSTRAINT [' + name + ']'
            FROM sys.check_constraints
            WHERE parent_object_id = OBJECT_ID('vital_readings') AND name LIKE '%type%'
            IF @sql IS NOT NULL EXEC(@sql)
        ");

        DB::statement("
            ALTER TABLE vital_readings ADD CONSTRAINT vital_readings_type_check
            CHECK ([type] IN ('heart_rate','spo2','temperature','blood_pressure','steps','sleep_state','hrv'))
        ");
    }
};
