<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE patients ADD age_group VARCHAR(20) NULL");

        DB::statement("
            ALTER TABLE patients ADD CONSTRAINT patients_age_group_check
            CHECK (age_group IN ('infant','child','adolescent','adult'))
        ");

        // Backfill from existing date_of_birth
        DB::statement("
            UPDATE patients SET age_group = CASE
                WHEN date_of_birth IS NULL THEN NULL
                WHEN DATEDIFF(YEAR, date_of_birth, GETDATE()) < 2  THEN 'infant'
                WHEN DATEDIFF(YEAR, date_of_birth, GETDATE()) < 12 THEN 'child'
                WHEN DATEDIFF(YEAR, date_of_birth, GETDATE()) < 18 THEN 'adolescent'
                ELSE 'adult'
            END
        ");
    }

    public function down(): void
    {
        DB::statement("
            DECLARE @sql NVARCHAR(512)
            SELECT @sql = 'ALTER TABLE patients DROP CONSTRAINT [' + name + ']'
            FROM sys.check_constraints
            WHERE parent_object_id = OBJECT_ID('patients') AND name = 'patients_age_group_check'
            IF @sql IS NOT NULL EXEC(@sql)
        ");
        DB::statement("ALTER TABLE patients DROP COLUMN age_group");
    }
};
