<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('patient','doctor','checkin_user') NOT NULL");
            return;
        }

        // Drop every CHECK constraint on the role column (handles any auto-generated names)
        $constraints = DB::select("
            SELECT cc.name
            FROM sys.check_constraints cc
            JOIN sys.columns c ON cc.parent_object_id = c.object_id AND cc.parent_column_id = c.column_id
            WHERE cc.parent_object_id = OBJECT_ID(N'users') AND c.name = N'role'
        ");

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE [users] DROP CONSTRAINT [{$constraint->name}]");
        }

        DB::statement("
            ALTER TABLE [users]
            ADD CONSTRAINT [users_role_check]
            CHECK ([role] IN (N'patient', N'doctor', N'checkin_user'))
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('patient','doctor') NOT NULL");
            return;
        }

        DB::statement("IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = N'users_role_check')
            ALTER TABLE [users] DROP CONSTRAINT [users_role_check]");

        DB::statement("ALTER TABLE [users] ADD CONSTRAINT [users_role_check] CHECK ([role] IN (N'patient', N'doctor'))");
    }
};
