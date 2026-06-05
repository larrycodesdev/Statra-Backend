<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pid')->nullable()->unique()->after('uuid');
        });

        // Backfill PIDs for any checkin_user accounts created before this migration
        $users = DB::table('users')
            ->whereNull('pid')
            ->where('role', 'checkin_user')
            ->get(['id']);

        foreach ($users as $user) {
            do {
                $pid = 'STA-' . strtoupper(Str::random(6));
            } while (DB::table('users')->where('pid', $pid)->exists());

            DB::table('users')->where('id', $user->id)->update(['pid' => $pid]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pid');
        });
    }
};
