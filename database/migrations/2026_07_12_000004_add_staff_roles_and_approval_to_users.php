<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            // Drop existing role check constraint then expand to include staff roles
            $constraints = DB::select("
                SELECT cc.name FROM sys.check_constraints cc
                JOIN sys.columns c ON cc.parent_object_id = c.object_id AND cc.parent_column_id = c.column_id
                WHERE cc.parent_object_id = OBJECT_ID(N'users') AND c.name = N'role'
            ");
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE [users] DROP CONSTRAINT [{$constraint->name}]");
            }
            DB::statement("ALTER TABLE [users] ADD CONSTRAINT [users_role_check]
                CHECK ([role] IN ('patient','doctor','checkin_user','admin','staff','superadmin'))");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('hospital_id')->nullable();
            $table->string('approval_status', 20)->default('approved');
            $table->foreign('hospital_id')->references('id')->on('hospitals')->nullOnDelete();
        });

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_approval_status_check
                CHECK (approval_status IN ('pending','approved','rejected'))");
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['hospital_id']);
            $table->dropColumn(['hospital_id', 'approval_status']);
        });

        if (DB::getDriverName() !== 'sqlsrv') return;

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
