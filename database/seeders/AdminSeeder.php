<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrNew(['email' => 'admin@hellobroseven.test']);

        $user->forceFill([
            'name' => 'Admin',
            'username' => 'admin',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();
    }
}
