<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->json('condition')->nullable()->after('gender');
            $table->string('emergency_contact_email')->nullable()->after('emergency_contact_phone');
            $table->string('emergency_contact_address')->nullable()->after('emergency_contact_email');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_address');
        });

        // Expand genotype to include SD, SE, SO — SQL Server vs MySQL syntax
        $this->modifyEnumColumn('patients', 'genotype', ['SS', 'SC', 'SB', 'SD', 'SE', 'SO', 'other'], nullable: true);
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['condition', 'emergency_contact_email', 'emergency_contact_address', 'emergency_contact_relationship']);
        });

        $this->modifyEnumColumn('patients', 'genotype', ['SS', 'SC', 'SB', 'other'], nullable: true);
    }

    private function modifyEnumColumn(string $table, string $column, array $values, bool $nullable = false): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlsrv') {
            $constraint = "{$table}_{$column}_check";
            DB::statement("IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = N'{$constraint}' AND parent_object_id = OBJECT_ID(N'{$table}'))
                            ALTER TABLE [{$table}] DROP CONSTRAINT [{$constraint}]");
            $inList = "N'" . implode("', N'", $values) . "'";
            DB::statement("ALTER TABLE [{$table}] ADD CONSTRAINT [{$constraint}] CHECK ([{$column}] IN ({$inList}))");
        } else {
            $enumList  = "'" . implode("','", $values) . "'";
            $nullStr   = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` ENUM({$enumList}) {$nullStr}");
        }
    }
};
