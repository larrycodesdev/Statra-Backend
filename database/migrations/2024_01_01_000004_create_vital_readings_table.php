<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vital_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'heart_rate', 'spo2', 'temperature',
                'blood_pressure', 'steps', 'sleep_state', 'hrv',
            ]);
            $table->json('value');
            $table->string('unit')->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['patient_id', 'type', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vital_readings');
    }
};
