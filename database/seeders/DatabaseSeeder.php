<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MasterDataSeeder::class,
            PositionSeeder::class,
            CompanySeeder::class,
            FeatureSettingSeeder::class,
            WorkScheduleSeeder::class,
            SuperAdminSeeder::class,
            AdminSeeder::class,
            EmployeeSeeder::class,
        ]);
    }
}
