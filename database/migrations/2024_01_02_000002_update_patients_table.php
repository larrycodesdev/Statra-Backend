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
            $table->json('condition')->nullable()->after('gender');
            $table->string('emergency_contact_email')->nullable()->after('emergency_contact_phone');
            $table->string('emergency_contact_address')->nullable()->after('emergency_contact_email');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_address');
        });

        // Expand genotype enum to include SD, SE, SO
        DB::statement("ALTER TABLE patients MODIFY COLUMN genotype ENUM('SS','SC','SB','SD','SE','SO','other') NULL");
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['condition', 'emergency_contact_email', 'emergency_contact_address', 'emergency_contact_relationship']);
        });

        DB::statement("ALTER TABLE patients MODIFY COLUMN genotype ENUM('SS','SC','SB','other') NULL");
    }
};
