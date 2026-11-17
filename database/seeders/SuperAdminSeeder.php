<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrNew(['email' => 'superadmin@hellobroseven.test']);

        // Password di-hash otomatis oleh cast 'hashed' pada model User.
        $user->forceFill([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();
    }
}
