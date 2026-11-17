<?php

namespace App\Support;

use App\Models\FeatureSetting;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class SidebarMenu
{
    private const ADMIN = [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN];

    public static function for(User $user): array
    {
        $resolved = [];

        foreach (self::definition() as $item) {
            if ($entry = self::resolve($item, $user)) {
                $resolved[] = $entry;
            }
        }

        return $resolved;
    }

    private static function resolve(array $item, User $user): ?array
    {
        if (!empty($item['roles']) && !$user->hasRole(...$item['roles'])) {
            return null;
        }

        // Fitur nonaktif: menu benar-benar dihilangkan, bukan disabled.
        if (!empty($item['feature']) && !FeatureSetting::isEnabled($item['feature'])) {
            return null;
        }

        $children = [];
        foreach ($item['children'] ?? [] as $child) {
            if ($entry = self::resolve($child, $user)) {
                $children[] = $entry;
            }
        }

        if (isset($item['children']) && $children === []) {
            return null;
        }

        $route = $item['route'] ?? null;
        $exists = $route !== null && Route::has($route);
        $active = $exists && request()->routeIs(...($item['active'] ?? [$route]));

        return [
            'label' => $item['label'],
            'icon' => $item['icon'],
            'url' => $exists ? route($route) : '#',
            'soon' => $route !== null && !$exists,
            'active' => $active,
            'children' => $children,
            'open' => collect($children)->contains('active', true),
        ];
    }

    private static function definition(): array
    {
        return [
            // ---- Dashboard (satu per role) ----
            ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'route' => 'superadmin.dashboard', 'roles' => [User::ROLE_SUPER_ADMIN]],
            ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'route' => 'admin.dashboard', 'roles' => [User::ROLE_ADMIN]],
            ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'route' => 'employee.dashboard', 'roles' => [User::ROLE_EMPLOYEE]],

            // ---- Super Admin & Admin ----
            [
                'label' => 'Data Master', 'icon' => 'bi-database', 'roles' => self::ADMIN, 'feature' => 'master_data',
                'children' => [
                    ['label' => 'Jabatan', 'icon' => 'bi-award', 'route' => 'positions.index', 'active' => ['positions.*'], 'feature' => 'positions'],
                    ['label' => 'Departemen', 'icon' => 'bi-building', 'route' => 'departments.index', 'active' => ['departments.*'], 'feature' => 'departments'],
                    ['label' => 'Divisi', 'icon' => 'bi-diagram-3', 'route' => 'divisions.index', 'active' => ['divisions.*'], 'feature' => 'divisions'],
                    ['label' => 'Pendidikan', 'icon' => 'bi-mortarboard', 'route' => 'education-levels.index', 'active' => ['education-levels.*']],
                    ['label' => 'Status Karyawan', 'icon' => 'bi-person-badge', 'route' => 'employee-statuses.index', 'active' => ['employee-statuses.*']],
                    ['label' => 'Jenis Izin', 'icon' => 'bi-journal-text', 'route' => 'leave-types.index', 'active' => ['leave-types.*']],
                    ['label' => 'Potongan Gaji', 'icon' => 'bi-percent', 'route' => 'deduction-rules.index', 'active' => ['deduction-rules.*']],
                ],
            ],
            ['label' => 'Data Karyawan', 'icon' => 'bi-people', 'route' => 'employees.index', 'active' => ['employees.*'], 'roles' => self::ADMIN, 'feature' => 'employees'],
            ['label' => 'Data Wajah', 'icon' => 'bi-person-bounding-box', 'route' => 'faces.index', 'active' => ['faces.*'], 'roles' => self::ADMIN, 'feature' => 'face_recognition'],
            ['label' => 'Data Jadwal', 'icon' => 'bi-calendar-week', 'route' => 'schedules.index', 'active' => ['schedules.*'], 'roles' => self::ADMIN, 'feature' => 'schedules'],
            ['label' => 'Data Absensi', 'icon' => 'bi-clipboard-check', 'route' => 'attendance.index', 'active' => ['attendance.*'], 'roles' => self::ADMIN, 'feature' => 'attendance'],
            ['label' => 'Request Izin', 'icon' => 'bi-envelope-paper', 'route' => 'leave-requests.index', 'active' => ['leave-requests.*'], 'roles' => self::ADMIN, 'feature' => 'leave_requests'],
            ['label' => 'Laporan Kehadiran', 'icon' => 'bi-bar-chart-line', 'route' => 'reports.attendance', 'active' => ['reports.*'], 'roles' => self::ADMIN, 'feature' => 'attendance_reports'],
            ['label' => 'Data Salary', 'icon' => 'bi-cash-coin', 'route' => 'payroll.index', 'active' => ['payroll.*'], 'roles' => self::ADMIN, 'feature' => 'payroll'],
            ['label' => 'Profile Perusahaan', 'icon' => 'bi-buildings', 'route' => 'company.edit', 'active' => ['company.*'], 'roles' => self::ADMIN, 'feature' => 'company_profile'],
            // Tanpa 'feature': dua menu ini tidak boleh ikut dimatikan, dan hanya untuk Super Admin.
            ['label' => 'Pengaturan Fitur', 'icon' => 'bi-toggles', 'route' => 'feature-settings.index', 'active' => ['feature-settings.*'], 'roles' => [User::ROLE_SUPER_ADMIN]],
            ['label' => 'Audit Log', 'icon' => 'bi-journal-check', 'route' => 'audit-logs.index', 'active' => ['audit-logs.*'], 'roles' => [User::ROLE_SUPER_ADMIN]],

            // ---- Karyawan ----
            ['label' => 'Absensi', 'icon' => 'bi-camera-video', 'route' => 'employee.attendance', 'roles' => [User::ROLE_EMPLOYEE], 'feature' => 'attendance'],
            ['label' => 'Riwayat Kehadiran', 'icon' => 'bi-clock-history', 'route' => 'employee.history', 'roles' => [User::ROLE_EMPLOYEE], 'feature' => 'attendance'],
            ['label' => 'Request Izin', 'icon' => 'bi-envelope-paper', 'route' => 'employee.leave-requests.index', 'active' => ['employee.leave-requests.*'], 'roles' => [User::ROLE_EMPLOYEE], 'feature' => 'leave_requests'],
            ['label' => 'Jadwal Saya', 'icon' => 'bi-calendar-check', 'route' => 'employee.schedule', 'roles' => [User::ROLE_EMPLOYEE], 'feature' => 'schedules'],

            // ---- Semua role ----
            ['label' => 'Profile Saya', 'icon' => 'bi-person-circle', 'route' => 'profile.show', 'active' => ['profile.*']],
        ];
    }
}
