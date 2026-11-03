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
<div class="zeit-wrapper" style="color:#fff; width:100%; max-width: 42rem;">
    <header class="mb-4 flex items-center justify-between gap-3 sm:mb-6">
        <div class="flex items-center gap-3">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15 text-lg sm:h-11 sm:w-11 sm:text-xl"><i class="fas fa-user-clock"></i></span>
            <div>
                <div class="text-base font-bold leading-tight sm:text-lg">Zeiterfassung</div>
                <div class="zeit-terminal-subline text-xs text-white/70 sm:text-sm">{{ config('app.name') }}</div>
            </div>
        </div>
        <div class="text-right">
            <div class="text-2xl font-bold tabular-nums leading-none sm:text-3xl" id="uhr">{{ now()->format('H:i') }}</div>
            <div class="zeit-terminal-subline text-xs text-white/70 sm:text-sm">{{ now()->locale('de')->isoFormat('dddd, D. MMMM') }}</div>
        </div>
    </header>

    @if(session('Meldung'))
        <div class="zeit-terminal-message mb-2 max-w-full break-words whitespace-normal rounded-2xl px-3 py-2 text-sm font-medium leading-snug sm:mb-4 sm:px-4 sm:py-3 sm:text-base {{ in_array(session('type'), ['danger', 'warning']) ? 'bg-red-500/90' : 'bg-white/20' }}">
            {{ session('Meldung') }}
        </div>
    @endif
    @if($errors->any())
        <div class="zeit-terminal-errors mb-2 max-w-full break-words whitespace-normal rounded-2xl bg-red-500/90 px-3 py-2 text-sm font-medium leading-snug sm:mb-4 sm:px-4 sm:py-3 sm:text-base">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <main class="zeit-terminal-content">
        @yield('content')
    </main>
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
