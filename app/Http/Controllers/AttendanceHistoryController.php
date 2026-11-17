<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Support\AttendanceLabels;
use App\Support\DateInput;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Akun Anda belum terhubung dengan data karyawan. Hubungi administrator.');

        $fromInput = DateInput::date($request->query('from'));
        $toInput = DateInput::date($request->query('to'));
        $monthInput = DateInput::month($request->query('month'));

        if ($fromInput || $toInput) {
            $from = $fromInput ?? $toInput->copy()->startOfMonth();
            $to = $toInput ?? $fromInput->copy()->endOfMonth();
        } else {
            $anchor = $monthInput ?? today()->startOfMonth();
            $from = $anchor->copy()->startOfMonth();
            $to = $anchor->copy()->endOfMonth();
        }
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $status = in_array($request->query('status'), ['present', 'late', 'absent', 'leave'], true) ? $request->query('status') : '';

        // Selalu dibatasi pada karyawan yang sedang login.
        $base = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->when($status !== '', fn ($q) => $q->where('status', $status));

        $counts = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $records = (clone $base)
            ->with(['workSchedule', 'checkInLocation', 'checkOutLocation'])
            ->orderByDesc('attendance_date')
            ->paginate(15)
            ->withQueryString();

        $details = $records->getCollection()->mapWithKeys(fn (AttendanceRecord $a) => [$a->id => [
            'date' => $a->attendance_date->copy()->locale('id')->translatedFormat('l, d F Y'),
            'status' => AttendanceLabels::status($a->status),
            'mode' => $a->work_mode,
            'method' => AttendanceLabels::method($a->verification_method),
            'check_in' => $a->check_in_at?->format('H:i') ?? '-',
            'check_out' => $a->check_out_at?->format('H:i') ?? '-',
            'late' => $a->late_minutes > 0 ? $a->late_minutes.' menit' : '-',
            'schedule' => $a->workSchedule?->name ?? '-',
            'in_loc' => AttendanceLabels::locationText($a->checkInLocation),
            'out_loc' => AttendanceLabels::locationText($a->checkOutLocation),
            'in_map' => AttendanceLabels::mapUrl($a->checkInLocation),
            'out_map' => AttendanceLabels::mapUrl($a->checkOutLocation),
            'note' => $a->notes ?: '-',
            'correction' => $a->is_corrected ? 'Dikoreksi admin: '.($a->correction_reason ?: '-') : '-',
        ]]);

        return view('attendance.history', [
            'records' => $records,
            'details' => $details,
            'from' => $from,
            'to' => $to,
            'month' => $monthInput?->format('Y-m') ?? ($fromInput || $toInput ? '' : $from->format('Y-m')),
            'status' => $status,
            'counts' => $counts,
            'filtered' => $request->filled('month') || $request->filled('from') || $request->filled('to') || $status !== '',
        ]);
    }
}
