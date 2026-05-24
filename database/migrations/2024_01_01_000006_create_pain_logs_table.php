<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pain_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('pain_level'); // 1–10
            $table->json('location')->nullable(); // body map areas
            $table->text('notes')->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index(['patient_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pain_logs');
    }
};
