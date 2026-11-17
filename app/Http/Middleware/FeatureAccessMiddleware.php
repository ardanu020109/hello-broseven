<?php

namespace App\Http\Middleware;

use App\Models\FeatureSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureAccessMiddleware
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        foreach ($features as $key) {
            // Kunci yang tidak ada di database dianggap nonaktif (fail-closed).
            if (FeatureSetting::isEnabled($key)) {
                continue;
            }

            $name = FeatureSetting::query()->where('feature_key', $key)->value('name') ?? $key;
            $message = "Fitur \"{$name}\" sedang dinonaktifkan oleh Super Admin.";

            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $message, 'code' => 'feature_disabled'], 403);
            }

            abort(403, $message);
        }

        return $next($request);
    }
}
