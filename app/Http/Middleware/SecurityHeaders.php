<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = (string) config('security.csp_mode', 'report-only');
        $nonce = null;

        if (in_array($mode, ['report-only', 'enforce'], true)) {
            $nonce = base64_encode(random_bytes(16));
            view()->share('cspNonce', $nonce); // dipakai atribut nonce pada skrip inline
        }

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        // Kamera dan GPS hanya boleh dipakai oleh origin aplikasi ini
        $response->headers->set('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=()');
        // Halaman tidak boleh dicache: tombol Back setelah logout tidak menampilkan halaman lama
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');

        if ($nonce) {
            $response->headers->set(
                $mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only',
                $this->policy($nonce, $request->isSecure())
            );
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function policy(string $nonce, bool $secure): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'wasm-unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",   // atribut style="..." dipakai di beberapa tampilan
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        if ($secure) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
