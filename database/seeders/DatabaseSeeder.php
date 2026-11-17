<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && Company::query()->exists()) {
            $this->command?->error('Database produksi sudah berisi data. Seeder dibatalkan agar konfigurasi tidak tertimpa.');

            return;
        }

        $this->call([
            MasterDataSeeder::class,
            PositionSeeder::class,
            CompanySeeder::class,
            FeatureSettingSeeder::class,
            WorkScheduleSeeder::class,
        ]);

        if (app()->isProduction()) {
            $this->command?->warn('Akun default TIDAK dibuat di produksi. Buat Super Admin: php artisan app:create-super-admin');

            return;
        }

        $this->call([
            SuperAdminSeeder::class,
            AdminSeeder::class,
            EmployeeSeeder::class,
        ]);
    }
}
