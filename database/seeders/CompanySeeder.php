<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        // Koordinat di bawah hanya placeholder. Ganti dengan lokasi kantor asli
        // lewat menu Profile Perusahaan (Fase 9) sebelum menguji absensi.
        Company::updateOrCreate(['id' => 1], [
            'name' => 'Hello BroSeven!',
            'description' => 'Perusahaan yang berfokus pada pengelolaan kehadiran dan produktivitas karyawan.',
            'address' => 'Makassar, Sulawesi Selatan',
            'email' => 'info@hellobroseven.test',
            'phone' => '+62 411 000000',
            'website' => 'https://hellobroseven.test',
            'latitude' => -5.1476540,
            'longitude' => 119.4327310,
            'attendance_radius_meters' => 100,
            'max_gps_accuracy_meters' => 50,
            'face_match_threshold' => 0.500,
            'absent_cutoff_minutes' => 120,
            'timezone' => 'Asia/Makassar',
            'operating_start' => '08:00:00',
            'operating_end' => '17:00:00',
            'payroll_deduction_basis' => 'daily_salary',
        ]);
    }
}
