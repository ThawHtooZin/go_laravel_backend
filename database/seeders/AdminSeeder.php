<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Default Super Admin for local/dev. Change in production.
     * Super Admin can create and manage Admins; Admins use the dashboard for users/drivers/rides.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['phone' => '09999999999'],
            [
                'display_name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );
    }
}
