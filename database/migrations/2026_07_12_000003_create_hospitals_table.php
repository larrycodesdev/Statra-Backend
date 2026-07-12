<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE hospitals (
                id            BIGINT IDENTITY(1,1) PRIMARY KEY,
                name          NVARCHAR(255) NOT NULL,
                address       NVARCHAR(500) NULL,
                city          NVARCHAR(100) NULL,
                country       NVARCHAR(100) NULL,
                contact_email NVARCHAR(255) NULL,
                contact_phone NVARCHAR(30)  NULL,
                is_active     BIT NOT NULL DEFAULT 1,
                created_at    DATETIME2 NOT NULL DEFAULT GETUTCDATE(),
                updated_at    DATETIME2 NOT NULL DEFAULT GETUTCDATE()
            )
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS hospitals");
    }
};
