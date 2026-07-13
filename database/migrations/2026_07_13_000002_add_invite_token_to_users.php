<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users ADD invite_token NVARCHAR(64) NULL");
        DB::statement("ALTER TABLE users ADD invite_expires_at DATETIME2 NULL");
    }

    public function down(): void
    {
        DB::statement("IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'invite_token')
            ALTER TABLE users DROP COLUMN invite_token");
        DB::statement("IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'invite_expires_at')
            ALTER TABLE users DROP COLUMN invite_expires_at");
    }
};
