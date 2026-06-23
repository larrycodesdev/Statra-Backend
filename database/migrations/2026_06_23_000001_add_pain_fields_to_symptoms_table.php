<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('symptoms', function (Blueprint $table) {
            $table->json('pain_areas')->nullable()->after('body_locations');
            $table->unsignedTinyInteger('pain_level')->nullable()->after('pain_areas');
        });
    }

    public function down(): void
    {
        Schema::table('symptoms', function (Blueprint $table) {
            $table->dropColumn(['pain_areas', 'pain_level']);
        });
    }
};
