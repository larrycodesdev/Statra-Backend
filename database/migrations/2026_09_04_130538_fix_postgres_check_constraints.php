<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;

        // users.role — three earlier migrations were sqlsrv/mysql-only and
        // silently skipped on PostgreSQL, leaving the constraint at the
        // original two values.
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check
            CHECK (role IN ('patient','doctor','checkin_user','admin','staff','superadmin'))");

        // users.approval_status — never created on PostgreSQL
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_approval_status_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_approval_status_check
            CHECK (approval_status IN ('pending','approved','rejected'))");

        // vital_readings.type — calories and stress were never added
        DB::statement("ALTER TABLE vital_readings DROP CONSTRAINT IF EXISTS vital_readings_type_check");
        DB::statement("ALTER TABLE vital_readings ADD CONSTRAINT vital_readings_type_check
            CHECK (type IN ('heart_rate','spo2','temperature','blood_pressure',
                            'steps','sleep_state','hrv','calories','stress'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check
            CHECK (role IN ('patient','doctor'))");

        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_approval_status_check");

        DB::statement("ALTER TABLE vital_readings DROP CONSTRAINT IF EXISTS vital_readings_type_check");
        DB::statement("ALTER TABLE vital_readings ADD CONSTRAINT vital_readings_type_check
            CHECK (type IN ('heart_rate','spo2','temperature','blood_pressure',
                            'steps','sleep_state','hrv'))");
    }
};