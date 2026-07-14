<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'superadmin@statrahealth.com'],
            [
                'first_name'      => 'Super',
                'last_name'       => 'Admin',
                'role'            => 'superadmin',
                'password'        => 'Statra@super@2026',
                'approval_status' => 'approved',
            ]
        );
    }
}
