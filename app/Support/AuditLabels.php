<?php

namespace App\Support;

class AuditLabels
{
    public const ACTIONS = [
        'login' => 'Login',
        'logout' => 'Logout',
        'create' => 'Tambah data',
        'update' => 'Ubah data',
        'delete' => 'Hapus data',
        'toggle_active' => 'Aktif/nonaktifkan data',
        'activate' => 'Aktifkan karyawan',
        'deactivate' => 'Nonaktifkan karyawan',
        'reset_password' => 'Reset password karyawan',
        'view_document' => 'Buka dokumen karyawan',
        'assign_schedule' => 'Tugaskan jadwal',
        'end_assignment' => 'Akhiri penugasan jadwal',
        'delete_assignment' => 'Hapus penugasan jadwal',
        'register_face' => 'Registrasi wajah',
        'face_verify_failed' => 'Verifikasi wajah gagal',
        'approve_face' => 'Setujui data wajah',
        'reject_face' => 'Tolak data wajah',
        'deactivate_face' => 'Nonaktifkan data wajah',
        'check_in' => 'Absen masuk',
        'check_out' => 'Absen pulang',
        'auto_attendance' => 'Absensi otomatis (sistem)',
        'correct_attendance' => 'Koreksi absensi',
        'leave_sync' => 'Sinkron izin ke absensi',
        'submit_leave' => 'Ajukan izin',
        'cancel_leave' => 'Batalkan pengajuan izin',
        'approve_leave' => 'Setujui izin',
        'reject_leave' => 'Tolak izin',
        'view_leave_attachment' => 'Buka lampiran izin',
        'print_attendance_report' => 'Cetak laporan kehadiran',
        'export_attendance_report' => 'Export laporan kehadiran',
        'create_payroll' => 'Buat payroll',
        'recalculate_payroll' => 'Hitung ulang payroll',
        'finalize_payroll' => 'Finalisasi payroll',
        'pay_payroll' => 'Tandai payroll dibayar',
        'delete_payroll' => 'Hapus payroll',
        'update_payroll_basis' => 'Ubah dasar potongan gaji',
        'export_payroll' => 'Export payroll',
        'view_payslip' => 'Lihat slip gaji',
        'export_payslip' => 'Unduh slip gaji',
        'update_company' => 'Ubah profile perusahaan',
        'toggle_feature' => 'Ubah pengaturan fitur',
        'update_profile' => 'Ubah profil',
        'update_account' => 'Ubah akun',
        'change_password' => 'Ganti password',
    ];

    public const SUBJECTS = [
        'Employee' => 'Karyawan',
        'User' => 'Pengguna',
        'AttendanceRecord' => 'Absensi',
        'LeaveRequest' => 'Pengajuan izin',
        'PayrollPeriod' => 'Periode payroll',
        'Payroll' => 'Payroll karyawan',
        'WorkSchedule' => 'Jadwal kerja',
        'EmployeeSchedule' => 'Penugasan jadwal',
        'Holiday' => 'Hari libur',
        'EmployeeFaceDescriptor' => 'Data wajah',
        'Company' => 'Perusahaan',
        'FeatureSetting' => 'Fitur',
        'Position' => 'Jabatan',
        'Department' => 'Departemen',
        'Division' => 'Divisi',
        'EducationLevel' => 'Pendidikan',
        'EmployeeStatus' => 'Status karyawan',
        'LeaveType' => 'Jenis izin',
        'SalaryDeductionRule' => 'Aturan potongan',
    ];

    public static function action(?string $action): string
    {
        return $action ? (self::ACTIONS[$action] ?? $action) : '-';
    }

    public static function subject(?string $type, mixed $id): string
    {
        if (!$type) {
            return '-';
        }

        $base = class_basename($type);

        return (self::SUBJECTS[$base] ?? $base).($id ? ' #'.$id : '');
    }

    /** Samarkan nilai yang namanya menyiratkan data rahasia. */
    public static function redact(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $clean = [];
        foreach ($value as $key => $item) {
            $clean[$key] = preg_match('/pass|token|descriptor|secret/i', (string) $key)
                ? '[disembunyikan]'
                : self::redact($item);
        }

        return $clean;
    }

    public static function pretty(?array $value): string
    {
        if ($value === null || $value === []) {
            return '-';
        }

        return (string) json_encode(self::redact($value), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
