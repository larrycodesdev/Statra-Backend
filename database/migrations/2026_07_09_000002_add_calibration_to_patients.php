<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('calibration_status', 20)->default('calibrating');
            $table->dateTime('calibration_start_at')->nullable();
        });

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("ALTER TABLE patients ADD CONSTRAINT patients_calibration_status_check
                CHECK (calibration_status IN ('calibrating','active','stale'))");
        }
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['calibration_status', 'calibration_start_at']);
        });
    }
};
