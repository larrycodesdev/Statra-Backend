<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('allow_doctor_view_records')->default(true);
            $table->boolean('allow_doctor_view_data')->default(true);
            $table->boolean('share_symptom_pain_data')->default(true);
            $table->boolean('share_medication_records')->default(true);
            $table->boolean('reminder_enabled')->default(true);
            $table->boolean('smart_alert_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_settings');
    }
};
