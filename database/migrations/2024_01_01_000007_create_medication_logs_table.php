<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('medication_name');
            $table->string('dosage')->nullable();
            $table->timestamp('scheduled_at');
            $table->timestamp('taken_at')->nullable();
            $table->enum('status', ['taken', 'missed', 'pending'])->default('pending');
            $table->timestamps();

            $table->index(['patient_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_logs');
    }
};
