<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Patient snapshot (submitted by the check-in user)
            $table->string('pid');
            $table->string('name');
            $table->enum('genotype', ['SS', 'SC', 'SB+', 'SB0', 'Unknown'])->default('Unknown');
            $table->enum('meds', ['Yes', 'No', 'Missed']);

            // Wellbeing inputs
            $table->unsignedTinyInteger('pain');  // 0–10
            $table->enum('fatigue',   ['Low', 'Medium', 'High']);
            $table->enum('sleep',     ['Good', 'Okay', 'Poor']);
            $table->enum('hydration', ['Good', 'Okay', 'Low']);
            $table->string('condition'); // e.g. "No, I feel normal" | "Slightly different" | "Very different"

            // Symptoms & triggers (arrays stored as JSON)
            $table->json('symptoms')->nullable();
            $table->json('flags')->nullable();
            $table->json('triggers')->nullable();

            // Safety
            $table->string('safety')->default('None');
            $table->text('notes')->nullable();

            // Calculated risk result — always computed server-side, never trusted from client
            $table->unsignedSmallInteger('total');
            $table->string('display_score');    // "OVERRIDE" or stringified number
            $table->string('status');           // Stable | Watch closely | Elevated | Urgent
            $table->boolean('red_flag')->default(false);
            $table->string('reason');
            $table->json('scores');             // {pain, fatigue, sleep, hydration, symptoms, triggers}
            $table->decimal('geno_mult', 3, 2)->default(1.00);

            $table->timestamp('checked_in_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'checked_in_at']);
            $table->index('pid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
