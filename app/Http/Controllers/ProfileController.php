<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FeatureSetting;
use App\Services\FaceVerificationService;
use App\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private ScheduleService $schedules,
        private FaceVerificationService $faces,
    ) {
    }

    public function show(Request $request): View
    {
        $user = $request->user();

        $employee = $user->isEmployee()
            ? $user->employee()->with(['position', 'department', 'division', 'employeeStatus', 'educationLevel', 'supervisor'])->first()
            : null;

        return view('profile.show', [
            'user' => $user,
            'employee' => $employee,
            'resolved' => $employee ? $this->schedules->resolveFor($employee, today()) : null,
            'faceStatus' => $employee ? $this->faces->statusFor($employee) : null,
            'faceEnabled' => FeatureSetting::isEnabled('face_recognition'),
            'scheduleEnabled' => FeatureSetting::isEnabled('schedules'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isEmployee()) {
            return $this->updateEmployee($request);
        }

        $request->merge([
            'name' => trim((string) $request->input('name')),
            'username' => Str::lower(trim((string) $request->input('username'))),
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], [], ['name' => 'nama']);

        $old = $user->only(['name', 'username', 'email']);
        $user->update($data);

        AuditLog::record('update_account', $user, $old, $user->only(['name', 'username', 'email']));

        return back()->with('success', 'Akun berhasil diperbarui.');
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed', 'different:current_password'],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.max' => 'Password baru maksimal 100 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Password saat ini salah.'])->errorBag('password');
        }

        $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save(); // di-hash oleh cast

        // Sesi di perangkat lain diputus; sesi ini tetap berjalan.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }
        $request->session()->regenerate();

        AuditLog::record('change_password', $user);

        return back()->with('success', 'Password berhasil diganti. Sesi di perangkat lain telah diakhiri.');
    }

    /* ------------------------------------------------------------------ */

    /** Karyawan hanya boleh mengubah: telepon, alamat, kontak darurat, dan foto. */
    private function updateEmployee(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Akun Anda belum terhubung dengan data karyawan. Hubungi administrator.');

        $data = $request->validate([
            'phone' => ['nullable', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $attrs = [
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
        ];

        $old = $employee->only(array_keys($attrs));
        $oldPhoto = $employee->photo;
        $newPhoto = null;

        if ($request->hasFile('photo')) {
            $newPhoto = $request->file('photo')->store('employees/photos', 'public');
            $attrs['photo'] = $newPhoto;
        }

        try {
            $employee->update($attrs);
        } catch (\Throwable $e) {
            if ($newPhoto) {
                Storage::disk('public')->delete($newPhoto);
            }
            throw $e;
        }

        if ($newPhoto && $oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }

        AuditLog::record('update_profile', $employee, $old, Arr::only($attrs, array_keys($old)) + ['photo_changed' => $newPhoto !== null]);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }
}
