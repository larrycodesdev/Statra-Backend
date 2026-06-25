<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('relationship', 100)->nullable();
            $table->timestamps();
        });

        // Migrate any existing single-contact data from the flat columns
        DB::statement("
            INSERT INTO emergency_contacts (patient_id, name, phone, email, address, relationship, created_at, updated_at)
            SELECT id, emergency_contact_name, emergency_contact_phone,
                   emergency_contact_email, emergency_contact_address, emergency_contact_relationship,
                   GETDATE(), GETDATE()
            FROM patients
            WHERE emergency_contact_name IS NOT NULL
              AND emergency_contact_phone IS NOT NULL
        ");

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_email',
                'emergency_contact_address',
                'emergency_contact_relationship',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_email')->nullable();
            $table->string('emergency_contact_address')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
        });

        Schema::dropIfExists('emergency_contacts');
    }
};
