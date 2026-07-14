<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE patients ADD hospital_id BIGINT NULL");
        DB::statement("ALTER TABLE patients ADD assigned_nurse_id BIGINT NULL");
        DB::statement("ALTER TABLE patients ADD ward NVARCHAR(100) NULL");
        DB::statement("ALTER TABLE patients ADD admitted_at DATE NULL");

        DB::statement("
            ALTER TABLE patients ADD CONSTRAINT patients_hospital_id_foreign
            FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE NO ACTION
        ");
        DB::statement("
            ALTER TABLE patients ADD CONSTRAINT patients_assigned_nurse_id_foreign
            FOREIGN KEY (assigned_nurse_id) REFERENCES users(id) ON DELETE NO ACTION
        ");
    }

    public function down(): void
    {
        DB::statement("IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'patients_hospital_id_foreign')
            ALTER TABLE patients DROP CONSTRAINT patients_hospital_id_foreign");
        DB::statement("IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'patients_assigned_nurse_id_foreign')
            ALTER TABLE patients DROP CONSTRAINT patients_assigned_nurse_id_foreign");
        DB::statement("ALTER TABLE patients DROP COLUMN IF EXISTS hospital_id");
        DB::statement("ALTER TABLE patients DROP COLUMN IF EXISTS assigned_nurse_id");
        DB::statement("ALTER TABLE patients DROP COLUMN IF EXISTS ward");
        DB::statement("ALTER TABLE patients DROP COLUMN IF EXISTS admitted_at");
    }
};
