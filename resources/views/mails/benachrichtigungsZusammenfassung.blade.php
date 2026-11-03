<x-mail::message>
# Hallo {{ $name }},

hier ist Ihre Zusammenfassung der neuen Benachrichtigungen.

@foreach($gruppen as $gruppe)
## {{ $gruppe['label'] }}

@foreach($gruppe['eintraege'] as $eintrag)
@php
    $zeile = '['.e($eintrag['titel']).']('.$eintrag['url'].')'
        .($eintrag['text'] && $eintrag['text'] !== $eintrag['titel'] ? ' – '.e($eintrag['text']) : '')
        .' *('.e($eintrag['zeit']).')*';
@endphp
- {!! $zeile !!}
@endforeach

@endforeach
<x-mail::button :url="$indexUrl">
Alle ungelesenen anzeigen
</x-mail::button>

<small>Welche Benachrichtigungen sofort, gesammelt oder gar nicht per Mail kommen, legen Sie unter [Benachrichtigungen → Einstellungen]({{ $einstellungenUrl }}) fest.</small>
</x-mail::message>
