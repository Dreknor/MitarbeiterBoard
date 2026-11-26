@php
    $monStoerung = $monAnzeige->contains(fn ($d) => in_array($d['status'], ['fehler', 'betroffen'], true));
    $monZeit = fn ($t) => $t?->copy()->timezone(config('app.timezone'))->format($t->isToday() ? 'H:i' : 'd.m. H:i');
@endphp
<div class="mon-leiste {{ $monStoerung ? 'mon-leiste--stoerung' : '' }}" role="status" aria-live="polite">
    <span class="mon-titel">
        <i class="fas {{ $monStoerung ? 'fa-exclamation-triangle' : 'fa-heartbeat' }}" aria-hidden="true"></i>
        Systemstatus
    </span>

    @forelse($monAnzeige as $d)
        @php
            $titel = collect([
                $d['name'].': '.$d['text'].($d['seit'] ? ' seit '.$monZeit($d['seit']) : ''),
                $d['hinweis'],
                $d['pruefung'] ? 'Letzte Prüfung '.$monZeit($d['pruefung']).($d['latenz'] !== null ? ' · '.$d['latenz'].' ms' : '') : null,
            ])->filter()->implode("\n");
        @endphp
        <span class="mon-pill mon-pill--{{ $d['status'] }}" title="{{ $titel }}">
            <span class="mon-punkt" aria-hidden="true"></span>
            <span>{{ $d['name'] }}</span>
            @if($d['status'] === 'ok')
                <span class="mon-sr">in Betrieb</span>
            @else
                <span class="mon-zusatz">– {{ $d['text'] }}</span>
            @endif
        </span>
    @empty
        <span class="mon-leer">Noch keine Dienste eingerichtet.</span>
    @endforelse

    <span class="mon-stand">Stand {{ now()->format('H:i') }}</span>
    @if($monVerwalten)
        <a href="{{ url('settings/Monitoring') }}" class="mon-link" title="Monitoring verwalten" aria-label="Monitoring verwalten">
            <i class="fas fa-cog" aria-hidden="true"></i>
        </a>
    @endif
</div>
