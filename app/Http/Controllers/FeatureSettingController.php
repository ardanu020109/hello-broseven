<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FeatureSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FeatureSettingController extends Controller
{
    /** Dampak penonaktifan, ditampilkan agar Super Admin tahu apa yang akan terblokir. */
    private const IMPACT = [
        'master_data' => 'Seluruh menu Data Master dan URL-nya (jabatan, departemen, divisi, pendidikan, status karyawan, jenis izin, potongan gaji).',
        'positions' => 'Halaman Data Jabatan. Data Master harus aktif agar halaman ini dapat dibuka.',
        'departments' => 'Halaman Data Departemen. Data Master harus aktif agar halaman ini dapat dibuka.',
        'divisions' => 'Halaman Data Divisi. Data Master harus aktif agar halaman ini dapat dibuka.',
        'employees' => 'Halaman Data Karyawan (daftar, tambah, ubah, detail, reset password, dokumen).',
        'schedules' => 'Data Jadwal (jadwal, penugasan, kalender, hari libur) untuk admin, dan Jadwal Saya untuk karyawan.',
        'attendance' => 'Data Absensi (admin), serta Absensi dan Riwayat Kehadiran (karyawan), termasuk endpoint absen masuk dan pulang.',
        'face_recognition' => 'Menu Data Wajah dan registrasi wajah. Absensi berjalan TANPA verifikasi wajah.',
        'gps_verification' => 'Tidak ada halaman. Absensi tidak lagi memeriksa lokasi dan radius kantor.',
        'leave_requests' => 'Request Izin untuk admin (persetujuan) dan karyawan (pengajuan).',
        'attendance_reports' => 'Laporan Kehadiran beserta export dan cetak.',
        'payroll' => 'Data Salary: periode payroll, slip gaji, dan export. Kartu gaji di dashboard ikut disembunyikan.',
        'company_profile' => 'Halaman Profile Perusahaan.',
    ];

    /** Fitur yang bila dimatikan melemahkan keamanan absensi. */
    private const SECURITY = ['face_recognition', 'gps_verification'];

    public function index(): View
    {
        $features = FeatureSetting::query()->orderBy('sort_order')->orderBy('id')->get();

        return view('feature-settings.index', [
            'features' => $features,
            'impact' => self::IMPACT,
            'security' => self::SECURITY,
            'enabledCount' => $features->where('is_enabled', true)->count(),
        ]);
    }

    public function toggle(string $id): RedirectResponse
    {
        $feature = FeatureSetting::findOrFail($id);
        $was = (bool) $feature->is_enabled;

        // Lewat instance (bukan query massal) agar cache fitur ikut dibersihkan.
        $feature->update(['is_enabled' => !$was]);

        AuditLog::record('toggle_feature', $feature, ['is_enabled' => $was], ['is_enabled' => !$was, 'feature' => $feature->feature_key]);

        return back()->with(
            $was ? 'warning' : 'success',
            "Fitur \"{$feature->name}\" ".($was ? 'dinonaktifkan untuk semua pengguna.' : 'diaktifkan kembali.')
        );
    }
}
