{{--
    Klassenraum-Tools: eigenständige Vollbildseite (ohne Sidebar/Topbar) für Beamer und Tafel.
    Lautstärke-Tools teilen sich die Oberfläche aus partials/lautstaerke, alle anderen
    Tools bringen ihr Markup in tools/{schluessel}.blade.php mit.
--}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0f172a">
    <title>{{ $tool['titel'] }} · Klassenraum-Tools</title>
    <link rel="shortcut icon" href="{{ asset('img/'.config('app.favicon')) }}" type="image/x-icon">
    <link href="{{ asset('css/all.css') }}" rel="stylesheet">
    <link href="{{ asset('css/solid.css') }}" rel="stylesheet">
    @vite(['resources/css/klassenraum-tool.css', $tool['entry']])
</head>
<body class="krt krt-tool-{{ $schluessel }} {{ $tool['mikrofon'] ? 'krt-dunkel' : 'krt-hell' }}">
    @if($tool['mikrofon'])
        @include('klassenraum.partials.lautstaerke')
    @else
        @include('klassenraum.tools.'.$schluessel)
    @endif

    <noscript>
        <div class="krt-noscript">Dieses Tool benötigt JavaScript.</div>
    </noscript>
</body>
</html>
