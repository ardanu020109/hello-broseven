<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') &middot; @yield('title') - {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/logo.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/components.css') }}">
</head>
<body>
    <div class="hb-error">
        <div class="hb-card hb-error-card text-center">
            <div class="hb-error-code">@yield('code')</div>
            <h1 class="h4 mb-2">@yield('title')</h1>
            <p class="text-muted mb-4">@yield('message')</p>
            <a href="{{ url('/') }}" class="hb-btn hb-btn-primary"><i class="bi bi-house-door me-1"></i> Kembali ke beranda</a>
        </div>
    </div>
</body>
</html>
