<?php

use App\Http\Middleware\FeatureAccessMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'feature' => FeatureAccessMiddleware::class,
        ]);

        // Header keamanan + no-store untuk seluruh respons web
        $middleware->appendToGroup('web', SecurityHeaders::class);

        // Tamu yang membuka halaman terproteksi diarahkan ke login;
        // pengguna yang sudah login dan membuka /login diarahkan ke dashboard role-nya.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
