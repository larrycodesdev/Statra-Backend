<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->unsignedBigInteger('hospital_id')->nullable();
            $table->unsignedBigInteger('assigned_nurse_id')->nullable();
            $table->string('ward', 100)->nullable();
            $table->date('admitted_at')->nullable();
            $table->foreign('hospital_id')->references('id')->on('hospitals')->nullOnDelete();
            $table->foreign('assigned_nurse_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['hospital_id']);
            $table->dropForeign(['assigned_nurse_id']);
            $table->dropColumn(['hospital_id', 'assigned_nurse_id', 'ward', 'admitted_at']);
        });
    }
};
