<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend status enum — Azure SQL stores enums as varchar + check constraint.
        // Drop the generated constraint, alter the column, recreate with new values.
        DB::statement("
            DECLARE @con NVARCHAR(200)
            SELECT @con = name
            FROM sys.check_constraints
            WHERE parent_object_id = OBJECT_ID('band_orders')
              AND COL_NAME(parent_object_id, parent_column_id) = 'status'
            IF @con IS NOT NULL
                EXEC('ALTER TABLE band_orders DROP CONSTRAINT [' + @con + ']')
        ");

        DB::statement("ALTER TABLE band_orders ALTER COLUMN status NVARCHAR(50) NOT NULL");

        DB::statement("
            ALTER TABLE band_orders
            ADD CONSTRAINT band_orders_status_check CHECK (
                status IN (
                    'pending','paid','processing',
                    'packed','dispatched','in_transit',
                    'shipped','delivered','cancelled','delayed'
                )
            )
        ");

        Schema::table('band_orders', function (Blueprint $table) {
            // Issue flag — separate from status
            $table->string('issue', 30)->nullable()->after('status');          // damaged, lost
            $table->string('delay_note')->nullable()->after('issue');          // free-text note shown on tracking
            $table->json('status_history')->nullable()->after('delay_note');   // [{status, at, note?}]
            $table->string('country', 100)->nullable()->after('state');
            $table->unsignedTinyInteger('rating')->nullable()->after('shipped_at');
            $table->text('review_text')->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('band_orders', function (Blueprint $table) {
            $table->dropColumn(['issue', 'delay_note', 'status_history', 'country', 'rating', 'review_text']);
        });

        // Revert status column to original enum values
        DB::statement("
            DECLARE @con NVARCHAR(200)
            SELECT @con = name
            FROM sys.check_constraints
            WHERE parent_object_id = OBJECT_ID('band_orders')
              AND COL_NAME(parent_object_id, parent_column_id) = 'status'
            IF @con IS NOT NULL
                EXEC('ALTER TABLE band_orders DROP CONSTRAINT [' + @con + ']')
        ");

        DB::statement("
            ALTER TABLE band_orders
            ADD CONSTRAINT band_orders_status_check CHECK (
                status IN ('pending','paid','processing','shipped','delivered','cancelled')
            )
        ");
    }
};
