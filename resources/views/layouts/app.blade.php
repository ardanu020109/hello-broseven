<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/logo.svg') }}">

    {{-- Pulihkan status sidebar sebelum render agar tidak berkedip --}}
    <script nonce="{{ $cspNonce ?? '' }}">
        try {
            if (window.matchMedia('(min-width: 992px)').matches && localStorage.getItem('hb.sidebar') === 'collapsed') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        } catch (e) {}
    </script>

    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/layout.css') }}">
    @stack('styles')
</head>
<body class="hb-body">
    <div class="hb-shell">
        @include('layouts.partials.sidebar')

        <div class="hb-main">
            @include('layouts.partials.navbar')

            <main class="hb-content">
                <div class="hb-page-head">
                    <h1 class="hb-page-title">@yield('page_title', 'Dashboard')</h1>
                    @yield('page_actions')
                </div>

                @yield('content')
            </main>

            <footer class="hb-footer">
                &copy; {{ date('Y') }} {{ config('app.name') }}. Seluruh hak dilindungi.
            </footer>
        </div>
    </div>

    @include('components.toast-container')
    @include('components.loading')
    @include('components.flash')

    <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/core.js') }}"></script>
    <script src="{{ asset('assets/js/layout.js') }}"></script>
    @stack('scripts')
</body>
</html>
