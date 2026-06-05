<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlsrv') {
            // Drop whatever the auto-generated constraint name is
            DB::statement("
                DECLARE @name NVARCHAR(256)
                SELECT @name = cc.name
                FROM sys.check_constraints cc
                JOIN sys.columns c ON cc.parent_object_id = c.object_id AND cc.parent_column_id = c.column_id
                WHERE cc.parent_object_id = OBJECT_ID(N'users') AND c.name = N'role'
                IF @name IS NOT NULL
                    EXEC('ALTER TABLE [users] DROP CONSTRAINT [' + @name + ']')
            ");

            DB::statement("
                ALTER TABLE [users]
                ADD CONSTRAINT [users_role_check]
                CHECK ([role] IN (N'patient', N'doctor', N'checkin_user'))
            ");
        } else {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('patient','doctor','checkin_user') NOT NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlsrv') {
            DB::statement("
                IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = N'users_role_check')
                    ALTER TABLE [users] DROP CONSTRAINT [users_role_check]
            ");
            DB::statement("
                ALTER TABLE [users]
                ADD CONSTRAINT [users_role_check]
                CHECK ([role] IN (N'patient', N'doctor'))
            ");
        } else {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('patient','doctor') NOT NULL");
        }
    }
};
