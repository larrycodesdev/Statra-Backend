<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing role check constraint
        $constraints = DB::select("
            SELECT cc.name
            FROM sys.check_constraints cc
            JOIN sys.columns c ON cc.parent_object_id = c.object_id AND cc.parent_column_id = c.column_id
            WHERE cc.parent_object_id = OBJECT_ID(N'users') AND c.name = N'role'
        ");
        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE [users] DROP CONSTRAINT [{$constraint->name}]");
        }

        // Expand role to include all staff roles
        DB::statement("
            ALTER TABLE [users]
            ADD CONSTRAINT [users_role_check]
            CHECK ([role] IN ('patient','doctor','checkin_user','admin','staff','superadmin'))
        ");

        // hospital_id — links admin/doctor/staff to their hospital; null for superadmin/patient
        DB::statement("ALTER TABLE users ADD hospital_id BIGINT NULL");
        DB::statement("
            ALTER TABLE users ADD CONSTRAINT users_hospital_id_foreign
            FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL
        ");

        // approval_status — doctors and staff must be approved by admin before accessing the system
        DB::statement("ALTER TABLE users ADD approval_status NVARCHAR(20) NOT NULL DEFAULT 'approved'");
        DB::statement("
            ALTER TABLE users ADD CONSTRAINT users_approval_status_check
            CHECK (approval_status IN ('pending','approved','rejected'))
        ");

        // Set pending for any future doctor/staff registrations via trigger logic (handled in app layer)
        // Existing users stay 'approved' (default above covers them)
    }

    public function down(): void
    {
        DB::statement("IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'users_approval_status_check')
            ALTER TABLE users DROP CONSTRAINT users_approval_status_check");
        DB::statement("IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'users_hospital_id_foreign')
            ALTER TABLE users DROP CONSTRAINT users_hospital_id_foreign");
        DB::statement("IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'approval_status')
            ALTER TABLE users DROP COLUMN approval_status");
        DB::statement("IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'hospital_id')
            ALTER TABLE users DROP COLUMN hospital_id");

        // Restore original role constraint
        $constraints = DB::select("
            SELECT cc.name FROM sys.check_constraints cc
            JOIN sys.columns c ON cc.parent_object_id = c.object_id AND cc.parent_column_id = c.column_id
            WHERE cc.parent_object_id = OBJECT_ID(N'users') AND c.name = N'role'
        ");
        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE [users] DROP CONSTRAINT [{$constraint->name}]");
        }
        DB::statement("ALTER TABLE [users] ADD CONSTRAINT [users_role_check]
            CHECK ([role] IN ('patient','doctor','checkin_user'))");
    }
};
