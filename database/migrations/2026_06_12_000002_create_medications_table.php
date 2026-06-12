<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('dosage')->nullable();
            $table->string('time_to_use')->nullable(); // before_breakfast, after_lunch, before_bed, etc.
            $table->string('frequency')->nullable();   // once_daily, twice_daily, three_times_daily, etc.
            $table->unsignedTinyInteger('frequency_count')->default(1);
            $table->date('begin_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('reminder_times')->nullable(); // ["05:00", "14:00"]
            $table->boolean('remind_me')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['patient_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
