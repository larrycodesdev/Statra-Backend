<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE appointments ADD type NVARCHAR(100) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE appointments DROP COLUMN IF EXISTS type");
    }
};
