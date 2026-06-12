<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQL Server unique indexes include NULLs, so multiple NULL pids fail.
        // Replace with a filtered index that only enforces uniqueness on non-NULL values.
        DB::statement('DROP INDEX IF EXISTS users_pid_unique ON [users]');
        DB::statement('CREATE UNIQUE INDEX users_pid_unique ON [users] (pid) WHERE pid IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_pid_unique ON [users]');
        DB::statement('CREATE UNIQUE INDEX users_pid_unique ON [users] (pid)');
    }
};
