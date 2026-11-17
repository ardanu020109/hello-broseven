<?php

namespace Database\Seeders;

use App\Models\FeatureSetting;
use Illuminate\Database\Seeder;

class FeatureSettingSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            ['master_data', 'Data Master', 'Mengelola seluruh data referensi: jabatan, departemen, divisi, pendidikan, status karyawan, jenis izin, dan potongan gaji.'],
            ['positions', 'Data Jabatan', 'Mengelola jabatan beserta gaji harian per jabatan.'],
            ['departments', 'Data Departemen', 'Mengelola departemen perusahaan.'],
            ['divisions', 'Data Divisi', 'Mengelola divisi di bawah departemen.'],
            ['employees', 'Data Karyawan', 'Mengelola data karyawan, akun login, dan dokumen.'],
            ['schedules', 'Data Jadwal', 'Mengelola jadwal kerja, shift, hari libur, dan penugasan jadwal.'],
            ['attendance', 'Data Absensi', 'Melihat, memfilter, dan mengoreksi data absensi serta menjalankan absensi karyawan.'],
            ['face_recognition', 'Face Recognition', 'Verifikasi identitas karyawan dengan pengenalan wajah saat absensi.'],
            ['gps_verification', 'GPS Verification', 'Verifikasi lokasi absensi dengan geofence radius kantor. Karyawan WFH dikecualikan sesuai jadwalnya.'],
            ['leave_requests', 'Request Izin', 'Pengajuan dan persetujuan izin, sakit, dan cuti.'],
            ['attendance_reports', 'Laporan Kehadiran', 'Rekap dan laporan kehadiran beserta export.'],
            ['payroll', 'Data Salary', 'Perhitungan gaji harian, payroll bulanan, dan slip gaji.'],
            ['company_profile', 'Profile Perusahaan', 'Pengaturan identitas perusahaan, lokasi kantor, dan radius absensi.'],
        ];

        foreach ($features as $i => [$key, $name, $description]) {
            FeatureSetting::updateOrCreate(
                ['feature_key' => $key],
                ['name' => $name, 'description' => $description, 'is_enabled' => true, 'sort_order' => $i + 1]
            );
        }
    }
}
