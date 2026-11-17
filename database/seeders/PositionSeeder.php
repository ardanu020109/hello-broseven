<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        // Nominal hanya contoh awal; super admin dapat mengubahnya dari Data Master.
        foreach ([
            ['STF', 'Staff', 100000],
            ['SPV', 'Supervisor', 150000],
            ['MGR', 'Manager', 200000],
        ] as [$code, $name, $salary]) {
            Position::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'daily_salary' => $salary, 'is_active' => true]
            );
        }
    }
}
