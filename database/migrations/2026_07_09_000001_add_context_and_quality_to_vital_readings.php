<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vital_readings', function (Blueprint $table) {
            $table->string('activity_context', 20)->nullable();
            $table->string('quality_flag', 20)->default('good');
        });

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("ALTER TABLE vital_readings ADD CONSTRAINT vital_readings_activity_context_check
                CHECK (activity_context IN ('resting','light','active','sleep','unknown'))");
            DB::statement("ALTER TABLE vital_readings ADD CONSTRAINT vital_readings_quality_flag_check
                CHECK (quality_flag IN ('good','low_confidence','motion_artifact'))");
        }
    }

    public function down(): void
    {
        Schema::table('vital_readings', function (Blueprint $table) {
            $table->dropColumn(['activity_context', 'quality_flag']);
        });
    }
};
