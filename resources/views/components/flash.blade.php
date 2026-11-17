
<script nonce="{{ $cspNonce ?? '' }}">
    window.HB_FLASH = {{ Illuminate\Support\Js::from([
        'success' => session('success'),
        'error' => session('error'),
        'warning' => session('warning'),
        'info' => session('info'),
    ]) }};
</script>

