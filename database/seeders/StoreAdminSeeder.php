<?php

namespace Database\Seeders;

use App\Models\StoreAdmin;
use Illuminate\Database\Seeder;

class StoreAdminSeeder extends Seeder
{
    public function run(): void
    {
        StoreAdmin::firstOrCreate(
            ['email' => 'superadmin@statrahealth.com'],
            [
                'name'     => 'Super Admin',
                'password' => 'Statra@admin@token@forever@2026',
            ]
        );
    }
}
