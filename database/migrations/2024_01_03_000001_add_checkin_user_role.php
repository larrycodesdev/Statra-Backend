<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Extend the role enum to include checkin_user
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('patient','doctor','checkin_user') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('patient','doctor') NOT NULL");
    }
};
