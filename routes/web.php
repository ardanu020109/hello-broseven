<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceHistoryController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\EducationLevelController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeStatusController;
use App\Http\Controllers\FaceRecognitionController;
use App\Http\Controllers\FeatureSettingController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\MyLeaveRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SalaryDeductionRuleController;
use App\Http\Controllers\ScheduleAssignmentController;
use App\Http\Controllers\ScheduleCalendarController;
use App\Http\Controllers\WorkScheduleController;
use Illuminate\Support\Facades\Route;

// Landing page publik
Route::get('/', [LandingController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ===== Semua role: profil dan notifikasi =====
    Route::middleware('role:super_admin,admin,employee')->group(function () {
        Route::prefix('profile')->name('profile.')->controller(ProfileController::class)->group(function () {
            Route::get('/', 'show')->name('show');
            Route::put('/', 'update')->name('update');
            Route::put('/password', 'password')->middleware('throttle:6,1')->name('password');
        });

        Route::prefix('notifications')->name('notifications.')->controller(NotificationController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/read-all', 'readAll')->name('read-all');
            Route::post('/{id}/read', 'read')->whereNumber('id')->name('read');
            Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
        });
    });

    // Setiap dashboard dikunci ke satu role: mengubah URL ke role lain => 403.
    Route::middleware('role:super_admin')->prefix('super-admin')->name('superadmin.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'superAdmin'])->name('dashboard');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');
    });

    // ===== Hanya Super Admin: Pengaturan Fitur dan Audit Log (tidak ikut dimatikan oleh fitur) =====
    Route::middleware('role:super_admin')->group(function () {
        Route::prefix('feature-settings')->name('feature-settings.')->controller(FeatureSettingController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::patch('/{id}/toggle', 'toggle')->whereNumber('id')->name('toggle');
        });

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    // ===== Karyawan =====
    Route::middleware('role:employee')->prefix('employee')->name('employee.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'employee'])->name('dashboard');

        Route::middleware('feature:schedules')->get('/schedule', [ScheduleCalendarController::class, 'mine'])->name('schedule');

        // Absensi dan riwayat
        Route::middleware('feature:attendance')->group(function () {
            Route::get('/attendance', [AttendanceController::class, 'show'])->name('attendance');
            Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])
                ->middleware('throttle:20,1')->name('attendance.check-in');
            Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])
                ->middleware('throttle:20,1')->name('attendance.check-out');

            Route::get('/history', [AttendanceHistoryController::class, 'index'])->name('history');
        });

        // Request izin milik sendiri
        Route::middleware('feature:leave_requests')->prefix('leave-requests')->name('leave-requests.')
            ->controller(MyLeaveRequestController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
                Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
                Route::get('/{id}/attachment', 'attachment')->whereNumber('id')->name('attachment');
            });

        // Registrasi dan verifikasi wajah
        Route::middleware('feature:face_recognition')->group(function () {
            Route::get('/face', [FaceRecognitionController::class, 'register'])->name('face');
            Route::post('/face', [FaceRecognitionController::class, 'store'])
                ->middleware('throttle:10,1')->name('face.store');
            Route::post('/face/verify', [FaceRecognitionController::class, 'verify'])
                ->middleware('throttle:40,1')->name('face.verify');
        });
    });

    // ===== Super Admin & Admin =====
    Route::middleware('role:super_admin,admin')->group(function () {

        // Data Master: nama route = {uri}.index/store/update/destroy/toggle
        // Jabatan, Departemen, dan Divisi butuh fitur induk (master_data) dan fiturnya sendiri.
        $masters = [
            'positions' => [PositionController::class, 'master_data,positions'],
            'departments' => [DepartmentController::class, 'master_data,departments'],
            'divisions' => [DivisionController::class, 'master_data,divisions'],
            'education-levels' => [EducationLevelController::class, 'master_data'],
            'employee-statuses' => [EmployeeStatusController::class, 'master_data'],
            'leave-types' => [LeaveTypeController::class, 'master_data'],
            'deduction-rules' => [SalaryDeductionRuleController::class, 'master_data'],
        ];

        foreach ($masters as $uri => [$controller, $features]) {
            Route::middleware('feature:'.$features)->prefix($uri)->name($uri.'.')->controller($controller)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('/{id}', 'update')->whereNumber('id')->name('update');
                Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
                Route::patch('/{id}/toggle', 'toggle')->whereNumber('id')->name('toggle');
            });
        }

        // Data Karyawan
        Route::middleware('feature:employees')->prefix('employees')->name('employees.')->controller(EmployeeController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{id}', 'show')->whereNumber('id')->name('show');
            Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
            Route::put('/{id}', 'update')->whereNumber('id')->name('update');
            Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
            Route::patch('/{id}/status', 'toggleStatus')->whereNumber('id')->name('toggle-status');
            Route::post('/{id}/reset-password', 'resetPassword')->whereNumber('id')->name('reset-password');
            Route::get('/{id}/files/{type}', 'file')->whereNumber('id')->whereIn('type', ['ktp', 'document'])->name('file');
        });

        // Data Wajah: persetujuan registrasi ulang dan penonaktifan (descriptor tidak pernah ditampilkan)
        Route::middleware('feature:face_recognition')->prefix('face-data')->name('faces.')->controller(FaceRecognitionController::class)->group(function () {
            Route::get('/', 'manage')->name('index');
            Route::post('/{id}/approve', 'approve')->whereNumber('id')->name('approve');
            Route::post('/{id}/reject', 'reject')->whereNumber('id')->name('reject');
            Route::post('/{id}/deactivate', 'deactivate')->whereNumber('id')->name('deactivate');
        });

        // Data Absensi (admin): daftar, detail, koreksi
        Route::middleware('feature:attendance')->prefix('attendance')->name('attendance.')->controller(AttendanceRecordController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{id}', 'show')->whereNumber('id')->name('show');
            Route::put('/{id}/correct', 'correct')->whereNumber('id')->name('correct');
        });

        // Request Izin (admin): daftar, detail, persetujuan
        Route::middleware('feature:leave_requests')->prefix('leave-requests')->name('leave-requests.')->controller(LeaveRequestController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{id}', 'show')->whereNumber('id')->name('show');
            Route::post('/{id}/approve', 'approve')->whereNumber('id')->name('approve');
            Route::post('/{id}/reject', 'reject')->whereNumber('id')->name('reject');
            Route::get('/{id}/attachment', 'attachment')->whereNumber('id')->name('attachment');
        });

        // Laporan Kehadiran
        Route::middleware('feature:attendance_reports')->group(function () {
            Route::get('/reports/attendance', [AttendanceReportController::class, 'index'])->name('reports.attendance');
            Route::get('/reports/attendance/print', [AttendanceReportController::class, 'print'])->name('reports.attendance.print');
            Route::get('/reports/attendance/export/{format}', [AttendanceReportController::class, 'export'])
                ->whereIn('format', ['xlsx', 'pdf'])->middleware('throttle:30,1')->name('reports.attendance.export');
            Route::get('/reports/attendance/{id}', [AttendanceReportController::class, 'detail'])
                ->whereNumber('id')->name('reports.attendance.detail');
        });

        // Data Salary / Payroll
        Route::middleware('feature:payroll')->prefix('payroll')->name('payroll.')->controller(PayrollController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
            // Hanya Super Admin yang boleh mengubah dasar potongan
            Route::post('/settings', 'settings')->middleware('role:super_admin')->name('settings');

            Route::get('/{id}', 'show')->whereNumber('id')->name('show');
            Route::post('/{id}/recalculate', 'recalculate')->whereNumber('id')->middleware('throttle:20,1')->name('recalculate');
            Route::post('/{id}/finalize', 'finalize')->whereNumber('id')->middleware('throttle:20,1')->name('finalize');
            Route::post('/{id}/paid', 'paid')->whereNumber('id')->name('paid');
            Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
            Route::get('/{id}/export/{format}', 'export')->whereNumber('id')->whereIn('format', ['xlsx', 'pdf'])
                ->middleware('throttle:30,1')->name('export');

            Route::get('/{id}/employees/{payrollId}', 'employee')->whereNumber(['id', 'payrollId'])->name('employee');
            Route::get('/{id}/employees/{payrollId}/slip', 'slip')->whereNumber(['id', 'payrollId'])->name('slip');
            Route::get('/{id}/employees/{payrollId}/slip.pdf', 'slipPdf')->whereNumber(['id', 'payrollId'])
                ->middleware('throttle:30,1')->name('slip.pdf');
        });

        // Profile Perusahaan
        Route::middleware('feature:company_profile')->prefix('company')->name('company.')->controller(CompanyController::class)->group(function () {
            Route::get('/', 'edit')->name('edit');
            Route::put('/', 'update')->name('update');
        });

        // Data Jadwal: jadwal kerja, penugasan, kalender, hari libur
        Route::middleware('feature:schedules')->prefix('schedules')->name('schedules.')->group(function () {
            Route::get('/', [WorkScheduleController::class, 'index'])->name('index');
            Route::get('/create', [WorkScheduleController::class, 'create'])->name('create');
            Route::post('/', [WorkScheduleController::class, 'store'])->name('store');

            Route::get('/calendar', [ScheduleCalendarController::class, 'index'])->name('calendar');

            Route::prefix('assignments')->name('assignments.')->controller(ScheduleAssignmentController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::patch('/{id}/end', 'end')->whereNumber('id')->name('end');
                Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
            });

            Route::prefix('holidays')->name('holidays.')->controller(HolidayController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('/{id}', 'update')->whereNumber('id')->name('update');
                Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
            });

            Route::get('/{id}/edit', [WorkScheduleController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('/{id}', [WorkScheduleController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [WorkScheduleController::class, 'destroy'])->whereNumber('id')->name('destroy');
            Route::patch('/{id}/toggle', [WorkScheduleController::class, 'toggle'])->whereNumber('id')->name('toggle');
        });
    });
});
