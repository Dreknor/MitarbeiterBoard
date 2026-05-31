@component('mail::message')
# Neuer Kommentar in Prozess

Hallo {{ $recipientName }},

**{{ $authorName }}** hat einen neuen Kommentar zum Schritt **„{{ $stepName }}"** im Prozess
**„{{ $procedureName }}"** hinterlassen:

> {!! nl2br(e($body)) !!}

@component('mail::button', ['url' => url('procedure/'.$procedureId.'/start')])
Zum Prozess
@endcomponent

Viele Grüße,
MitarbeiterBoard
@endcomponent

