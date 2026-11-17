<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(Request $request): View
    {
        $years = Holiday::query()
            ->selectRaw('YEAR(holiday_date) as y')->distinct()->pluck('y')
            ->map(fn ($y) => (int) $y)
            ->push(now()->year, now()->year + 1)
            ->unique()->sortDesc()->values();

        $selected = $request->query('year');
        if ($selected === 'all') {
            $year = null;
        } else {
            $year = in_array((int) $selected, $years->all(), true) ? (int) $selected : now()->year;
        }

        $term = trim((string) $request->query('q'));

        $holidays = Holiday::query()
            ->when($year, fn ($q) => $q->whereYear('holiday_date', $year))
            ->when($term !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderBy('holiday_date')
            ->paginate(15)
            ->withQueryString();

        return view('schedules.holidays', [
            'holidays' => $holidays,
            'years' => $years,
            'year' => $year,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $data['is_national'] = $request->boolean('is_national');

        $holiday = Holiday::create($data);
        AuditLog::record('create', $holiday, null, $data);

        return redirect()->route('schedules.holidays.index', ['year' => $holiday->holiday_date->year])
            ->with('success', "Hari libur \"{$holiday->name}\" berhasil ditambahkan.");
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $holiday = Holiday::findOrFail($id);
        $data = $this->validated($request, $holiday);
        $data['is_national'] = $request->boolean('is_national');

        $old = $holiday->only(array_keys($data));
        $holiday->update($data);

        AuditLog::record('update', $holiday, $old, $data);

        return back()->with('success', "Hari libur \"{$holiday->name}\" berhasil diperbarui.");
    }

    public function destroy(string $id): RedirectResponse
    {
        $holiday = Holiday::findOrFail($id);
        $old = $holiday->only(['holiday_date', 'name', 'description', 'is_national']);
        $holiday->delete();

        AuditLog::record('delete', $holiday, $old);

        return back()->with('success', "Hari libur \"{$holiday->name}\" berhasil dihapus.");
    }

    private function validated(Request $request, ?Holiday $holiday): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'holiday_date' => ['required', 'date', 'after_or_equal:2000-01-01', Rule::unique('holidays', 'holiday_date')->ignore($holiday?->id)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_national' => ['nullable', 'boolean'],
        ], [
            'holiday_date.unique' => 'Tanggal ini sudah terdaftar sebagai hari libur.',
        ], [
            'holiday_date' => 'tanggal',
            'name' => 'nama hari libur',
            'description' => 'keterangan',
            'is_national' => 'libur nasional',
        ]);
    }
}
