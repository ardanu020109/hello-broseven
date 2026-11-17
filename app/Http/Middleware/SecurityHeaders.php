<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Kamera dan GPS hanya boleh dipakai oleh origin aplikasi ini
        $response->headers->set('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=()');
        // Halaman tidak boleh dicache: tombol Back setelah logout tidak menampilkan halaman lama
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
