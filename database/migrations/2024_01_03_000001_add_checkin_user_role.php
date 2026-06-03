<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->modifyRoleEnum(['patient', 'doctor', 'checkin_user']);
    }

    public function down(): void
    {
        $this->modifyRoleEnum(['patient', 'doctor']);
    }

    private function modifyRoleEnum(array $values): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlsrv') {
            $constraint = 'users_role_check';
            DB::statement("IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = N'{$constraint}' AND parent_object_id = OBJECT_ID(N'users'))
                            ALTER TABLE [users] DROP CONSTRAINT [{$constraint}]");
            $inList = "N'" . implode("', N'", $values) . "'";
            DB::statement("ALTER TABLE [users] ADD CONSTRAINT [{$constraint}] CHECK ([role] IN ({$inList}))");
        } else {
            $enumList = "'" . implode("','", $values) . "'";
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM({$enumList}) NOT NULL");
        }
    }
};
