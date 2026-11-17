<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;        // per kombinasi login + IP
    private const DECAY_SECONDS = 60;
    private const IP_MAX_ATTEMPTS = 20;    // per IP, berapa pun username-nya
    private const IP_DECAY_SECONDS = 300;

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ], [
            'login.required' => 'Email atau username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $throttleKey = Str::transliterate(Str::lower($data['login']).'|'.$request->ip());
        $ipKey = 'login-ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)
            || RateLimiter::tooManyAttempts($ipKey, self::IP_MAX_ATTEMPTS)) {
            $seconds = max(RateLimiter::availableIn($throttleKey), RateLimiter::availableIn($ipKey));

            throw ValidationException::withMessages([
                'login' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // is_active => true: akun nonaktif ditolak dengan pesan yang sama
        // agar tidak membocorkan apakah akun tersebut ada.
        $credentials = [
            $field => $data['login'],
            'password' => $data['password'],
            'is_active' => true,
        ];

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
            RateLimiter::hit($ipKey, self::IP_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'login' => 'Email/username atau password salah, atau akun tidak aktif.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate(); // cegah session fixation

        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        AuditLog::record('login', $user);

        // Selalu ke dashboard sesuai role (bukan "intended") agar tidak
        // mendarat di halaman milik role lain.
        return redirect()->route($user->dashboardRouteName());
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->user()) {
            AuditLog::record('logout', $request->user());
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda telah keluar dari aplikasi.');
    }
}
