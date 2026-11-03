{{--
    Empfängerauswahl für den Einladungsversand. Steuert nur, wer die Einladung bekommt –
    der Meetingzugriff (Gruppenmitglieder, Eingeladene) bleibt unverändert.

    Erwartet: $recipients (MeetingService::invitationRecipients()), $isGroupMeeting (bool)
--}}
@php
    $alleIds   = $recipients->pluck('id')->map(fn ($id) => (string) $id)->values();
    $extraIds  = $recipients->where('extra', true)->pluck('id')->map(fn ($id) => (string) $id)->values();
@endphp
<div x-data="{
        alle: @js($alleIds),
        extra: @js($extraIds),
        auswahl: @js($alleIds),
        suche: '',
        setzen(ids) { this.auswahl = [...ids]; },
     }"
     x-init="$watch('auswahl', () => $dispatch('invite-count', auswahl.length))">
    <input type="hidden" name="select_recipients" value="1">

    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
        <span class="mtg-label mb-0">
            Empfänger (<span x-text="auswahl.length"></span> von {{ $recipients->count() }})
        </span>
        <div class="flex flex-wrap gap-1">
            <button type="button" class="mtg-btn mtg-btn-secondary mtg-btn-sm" @click="setzen(alle)">Alle</button>
            @if($isGroupMeeting && $extraIds->isNotEmpty() && $extraIds->count() < $alleIds->count())
                <button type="button" class="mtg-btn mtg-btn-secondary mtg-btn-sm" @click="setzen(extra)"
                        title="Nur Personen, die zusätzlich zur Gruppe eingeladen wurden">Nur zusätzlich Eingeladene</button>
            @endif
            <button type="button" class="mtg-btn mtg-btn-secondary mtg-btn-sm" @click="setzen([])">Keine</button>
        </div>
    </div>

    @if($recipients->count() > 8)
        <input type="search" class="mtg-input mb-2" placeholder="Person suchen …" x-model="suche" aria-label="Empfänger filtern">
    @endif

    <div class="border border-gray-200 rounded-lg overflow-y-auto mb-1" style="max-height: 14rem;">
        @foreach($recipients as $r)
            <label class="flex items-center gap-2 px-3 py-1.5 text-sm cursor-pointer hover:bg-gray-50 mb-0"
                   x-show="!suche || @js(mb_strtolower($r['name'])).includes(suche.toLowerCase())">
                <input type="checkbox" name="recipients[]" value="{{ $r['id'] }}" class="accent-blue-600" x-model="auswahl">
                <span class="flex-1">{{ $r['name'] }}</span>
                @if(! $r['email'])
                    <span class="text-xs text-red-600" title="Keine E-Mail-Adresse hinterlegt">ohne E-Mail</span>
                @elseif($isGroupMeeting && ! $r['extra'])
                    <span class="text-xs text-gray-400">Gruppe</span>
                @endif
            </label>
        @endforeach
    </div>
    <p class="mtg-hint mb-3">
        Nicht ausgewählte Personen erhalten keine Einladung, behalten aber den Zugriff auf das Meeting.
    </p>
</div>
