<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <link rel="shortcut icon" href="{{ asset('img/favicon.ico') }}" type="image/x-icon">
    {{-- Terminal kehrt nach 10 Minuten Inaktivität immer zum Start zurück --}}
    <meta http-equiv="refresh" content="600; URL={{ route('time_recording.start') }}">
    <title>Zeiterfassung – {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/all.css') }}">
    @vite(['resources/css/zeit.css'])
    <style>
        html, body { margin: 0; padding: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    </style>
</head>
<body class="zeit-terminal">
<div class="zeit-wrapper" style="color:#fff; max-width: 42rem;">
    <header class="flex items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <span class="inline-flex w-11 h-11 items-center justify-center rounded-2xl bg-white/15 text-xl"><i class="fas fa-user-clock"></i></span>
            <div>
                <div class="text-lg font-bold leading-tight">Zeiterfassung</div>
                <div class="text-sm text-white/70">{{ config('app.name') }}</div>
            </div>
        </div>
        <div class="text-right">
            <div class="text-3xl font-bold tabular-nums leading-none" id="uhr">{{ now()->format('H:i') }}</div>
            <div class="text-sm text-white/70">{{ now()->locale('de')->isoFormat('dddd, D. MMMM') }}</div>
        </div>
    </header>

    @if(session('Meldung'))
        <div class="mb-4 rounded-2xl px-4 py-3 text-base font-medium {{ in_array(session('type'), ['danger', 'warning']) ? 'bg-red-500/90' : 'bg-white/20' }}">
            {{ session('Meldung') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-2xl bg-red-500/90 px-4 py-3 text-base font-medium">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    @yield('content')
</div>
<script>
    (function () {
        const uhr = document.getElementById('uhr');
        setInterval(() => { uhr.textContent = new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }); }, 10000);
    })();
</script>
@stack('js')
</body>
</html>
