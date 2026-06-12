<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('symptoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('symptom');
            $table->unsignedTinyInteger('severity'); // 1–10
            $table->enum('severity_label', ['none', 'mild', 'moderate', 'severe'])->nullable();
            $table->json('body_locations')->nullable();
            $table->string('duration')->nullable(); // just_started, 1_2hrs, 12hrs, all_day, 2_plus_days
            $table->json('triggers')->nullable();
            $table->boolean('on_medication')->default(false);
            $table->text('notes')->nullable();
            $table->enum('mood', ['low', 'okay', 'alright', 'good'])->nullable();
            $table->unsignedTinyInteger('edit_count')->default(0);
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index(['patient_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('symptoms');
    }
};
