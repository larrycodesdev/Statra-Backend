<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // received_at = server clock at ingestion time; recorded_at = device clock
        // The gap between them reveals sync lag (e.g. device offline for hours)
        Schema::table('vital_readings', function (Blueprint $table) {
            $table->dateTime('received_at')->nullable();
        });

        // Backfill existing rows from created_at (best approximation)
        DB::statement("UPDATE vital_readings SET received_at = created_at WHERE received_at IS NULL");
    }

    public function down(): void
    {
        Schema::table('vital_readings', function (Blueprint $table) {
            $table->dropColumn('received_at');
        });
    }
};
