@can('view calendar')
<div class="card">
    <div class="card-header bg-gradient-directional-blue text-white">
        <h5>📅 Kalender</h5>
        @if($syncStatus && $syncStatus['zeige_warnung'])
            <span class="badge badge-danger" title="Sync-Fehler in den letzten 24h">
                {{ $syncStatus['fehler_24h'] }} Fehler
            </span>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="contailer-fluid">
            @if($naechsteTermine->isNotEmpty())
                <div class="list-group list-group-flush">
                    @foreach($naechsteTermine as $termin)
                        <a href="{{ route('calendar.index') }}"
                           class="list-group-item list-group-item-action d-flex align-items-start"
                           style="border-left: 3px solid {{ $termin->kalender->farbe ?? '#3b82f6' }}">
                            <div class="mr-3 text-center" style="min-width: 50px;">
                                <div class="font-weight-bold">
                                    {{ $termin->beginn->timezone('Europe/Berlin')->format('d.m.') }}
                                </div>
                                @if(!$termin->ganztaegig)
                                    <small class="text-muted">
                                        {{ $termin->beginn->timezone('Europe/Berlin')->format('H:i') }}
                                    </small>
                                @endif
                            </div>
                            <div class="flex-fill">
                                <div class="mb-0">{{ $termin->titel }}</div>
                                @if($termin->ort)
                                    <small class="text-muted">📍 {{ $termin->ort }}</small>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="p-3 text-muted text-center">
                    <small>Keine anstehenden Termine.</small>
                </div>
            @endif
        </div>
    </div>
    <div class="card-footer text-center">
        <a href="{{ route('calendar.index') }}" class="text-primary">
            → alle Termine anzeigen
        </a>
    </div>
</div>
@endcan

