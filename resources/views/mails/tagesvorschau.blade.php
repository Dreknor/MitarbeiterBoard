<x-mail::message>
# Hallo {{ $name }},

{{ $vorabend ? 'das steht morgen an' : 'das steht heute an' }}: **{{ $datum }}**

@if($vorabend)
*Hinweis: Vertretungen und Abwesenheiten können sich bis morgen früh noch ändern. Änderungen an Ihren Vertretungen erhalten Sie zusätzlich als Benachrichtigung.*
@endif

@foreach($bereiche as $bereich)
## {{ $bereich['label'] }}

@foreach($bereich['eintraege'] as $eintrag)
@php
    $zeile = ($eintrag['zeit'] ? '**'.e($eintrag['zeit']).'** · ' : '')
        .($eintrag['url'] ? '['.e($eintrag['titel']).']('.$eintrag['url'].')' : e($eintrag['titel']))
        .($eintrag['hervorheben'] ? ' ⚠️' : '')
        .($eintrag['details'] ? ' – '.e($eintrag['details']) : '');
@endphp
- {!! $zeile !!}
@endforeach

@endforeach
<x-mail::button :url="$tagUrl">
Im MitarbeiterBoard öffnen
</x-mail::button>

<small>Uhrzeit, Bereiche und Kalender dieser Übersicht können Sie unter [Benachrichtigungen → Einstellungen]({{ $einstellungenUrl }}) anpassen oder die Übersicht abbestellen.</small>
</x-mail::message>
