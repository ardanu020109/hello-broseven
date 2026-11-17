<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompanyController extends Controller
{
    private const TIMEZONES = [
        'Asia/Jakarta' => 'WIB (Asia/Jakarta)',
        'Asia/Makassar' => 'WITA (Asia/Makassar)',
        'Asia/Jayapura' => 'WIT (Asia/Jayapura)',
    ];

    private const TRACKED = [
        'name', 'description', 'address', 'email', 'phone', 'website', 'latitude', 'longitude',
        'attendance_radius_meters', 'max_gps_accuracy_meters', 'face_match_threshold',
        'absent_cutoff_minutes', 'timezone', 'operating_start', 'operating_end',
    ];

    public function edit(): View
    {
        return view('company.edit', [
            'company' => Company::current() ?? new Company(),
            'timezones' => $this->timezones(Company::current()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = Company::current() ?? new Company();
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:1000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'attendance_radius_meters' => ['required', 'integer', 'between:10,5000'],
            'max_gps_accuracy_meters' => ['required', 'integer', 'between:5,500'],
            'face_match_threshold' => ['required', 'numeric', 'between:0.30,0.80'],
            'absent_cutoff_minutes' => ['required', 'integer', 'between:0,720'],
            'timezone' => ['required', Rule::in(array_keys($this->timezones($company->exists ? $company : null)))],
            'operating_start' => ['nullable', 'date_format:H:i'],
            'operating_end' => ['nullable', 'date_format:H:i'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'website.url' => 'Website harus berupa alamat lengkap yang diawali http:// atau https://.',
            'operating_start.date_format' => 'Jam buka harus berformat JJ:MM.',
            'operating_end.date_format' => 'Jam tutup harus berformat JJ:MM.',
            'logo.mimes' => 'Logo harus berupa JPG, PNG, atau WEBP.',
            'logo.image' => 'Logo harus berupa gambar.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
        ], [
            'name' => 'nama perusahaan',
            'description' => 'deskripsi',
            'address' => 'alamat',
            'phone' => 'nomor telepon',
            'website' => 'website',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'attendance_radius_meters' => 'radius absensi',
            'max_gps_accuracy_meters' => 'batas akurasi GPS',
            'face_match_threshold' => 'ambang kecocokan wajah',
            'absent_cutoff_minutes' => 'batas waktu absent',
            'timezone' => 'zona waktu',
            'logo' => 'logo',
        ]);

        $this->crossCheck($data);

        $attrs = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'address' => $data['address'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'website' => $data['website'] ?? null,
            'latitude' => isset($data['latitude']) && $data['latitude'] !== '' ? round((float) $data['latitude'], 7) : null,
            'longitude' => isset($data['longitude']) && $data['longitude'] !== '' ? round((float) $data['longitude'], 7) : null,
            'attendance_radius_meters' => (int) $data['attendance_radius_meters'],
            'max_gps_accuracy_meters' => (int) $data['max_gps_accuracy_meters'],
            'face_match_threshold' => round((float) $data['face_match_threshold'], 3),
            'absent_cutoff_minutes' => (int) $data['absent_cutoff_minutes'],
            'timezone' => $data['timezone'],
            'operating_start' => !empty($data['operating_start']) ? $data['operating_start'].':00' : null,
            'operating_end' => !empty($data['operating_end']) ? $data['operating_end'].':00' : null,
        ];

        $old = $company->exists ? $company->only(self::TRACKED) : null;
        $oldLogo = $company->logo;
        $newLogo = null;

        if ($request->hasFile('logo')) {
            $newLogo = $request->file('logo')->store('company', 'public');
            $attrs['logo'] = $newLogo;
        } elseif ($request->boolean('remove_logo')) {
            $attrs['logo'] = null;
        }

        try {
            $company->fill($attrs)->save();
        } catch (\Throwable $e) {
            if ($newLogo) {
                Storage::disk('public')->delete($newLogo);
            }
            throw $e;
        }

        if ($oldLogo && array_key_exists('logo', $attrs) && $attrs['logo'] !== $oldLogo) {
            Storage::disk('public')->delete($oldLogo);
        }

        $company->refresh();
        $new = $company->only(self::TRACKED);
        $new['logo_changed'] = array_key_exists('logo', $attrs);

        AuditLog::record('update_company', $company, $old, $new);

        return redirect()->route('company.edit')->with('success', 'Profile perusahaan berhasil disimpan.');
    }

    /* ------------------------------------------------------------------ */

    private function timezones(?Company $company): array
    {
        $zones = self::TIMEZONES;
        if ($company?->timezone && !isset($zones[$company->timezone])) {
            $zones[$company->timezone] = $company->timezone;
        }

        return $zones;
    }

    /** Validasi antar-field yang tidak bisa dinyatakan dengan satu rule. */
    private function crossCheck(array $data): void
    {
        $errors = [];

        $hasLat = isset($data['latitude']) && $data['latitude'] !== '';
        $hasLng = isset($data['longitude']) && $data['longitude'] !== '';
        if ($hasLat xor $hasLng) {
            $errors[$hasLat ? 'longitude' : 'latitude'] = 'Latitude dan longitude harus diisi bersamaan, atau dikosongkan keduanya.';
        }

        $start = $data['operating_start'] ?? null;
        $end = $data['operating_end'] ?? null;
        if (($start === null || $start === '') xor ($end === null || $end === '')) {
            $errors['operating_end'] = 'Isi jam buka dan jam tutup, atau kosongkan keduanya.';
        } elseif ($start && $end && $end <= $start) {
            $errors['operating_end'] = 'Jam tutup harus setelah jam buka.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
