<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard)
    {
    }

    /** /dashboard: arahkan ke dashboard sesuai role. */
    public function index(): RedirectResponse
    {
        return redirect()->route(auth()->user()->dashboardRouteName());
    }

    public function superAdmin(): View
    {
        return view('dashboard.admin', [
            'heading' => 'Dashboard Super Admin',
            'data' => $this->dashboard->admin(),
        ]);
    }

    public function admin(): View
    {
        return view('dashboard.admin', [
            'heading' => 'Dashboard Admin',
            'data' => $this->dashboard->admin(),
        ]);
    }

    public function employee(): View
    {
        $employee = auth()->user()->employee()
            ->with(['position', 'department', 'division', 'employeeStatus'])
            ->first();

        abort_if(!$employee, 403, 'Akun Anda belum terhubung dengan data karyawan. Hubungi administrator.');

        return view('dashboard.employee', [
            'employee' => $employee,
            'data' => $this->dashboard->employee($employee),
        ]);
    }
}
