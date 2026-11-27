{{--
    Reiter „Monitoring“ in den Einstellungen (settings/Monitoring). Daten: MonitoringEinstellungenComposer.
    Eigene Formulare statt des generischen Settings-Formulars (Rollenauswahl, Dienste, Token).
--}}
@php
    $monZeit = fn ($t) => $t?->copy()->timezone(config('app.timezone'))->format('d.m.Y H:i');
    $monStatusKlasse = fn ($s) => match ($s) { 'ok' => 'success', 'fehler' => 'danger', default => 'secondary' };
    $monStatusText = fn ($s) => match ($s) { 'ok' => 'in Betrieb', 'fehler' => 'gestört', default => 'unbekannt' };
@endphp

@if(session('monitoring_token'))
    <div class="alert alert-warning">
        <h6 class="alert-heading mb-2"><i class="fas fa-key"></i> Neues Heartbeat-Token – nur jetzt sichtbar</h6>
        <p class="mb-2">In Home Assistant in die <code>secrets.yaml</code> eintragen (mit „Bearer “ davor):</p>
        <pre class="bg-white border rounded p-2 mb-0 user-select-all">mitarbeiterboard_token: "Bearer {{ session('monitoring_token') }}"</pre>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $fehler)
                <li>{{ $fehler }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Löschformulare liegen außerhalb des Hauptformulars (keine verschachtelten Formulare) --}}
@foreach($monDienste as $dienst)
    <form id="mon-loeschen-{{ $dienst->id }}" method="post" action="{{ route('monitoring.dienste.loeschen', $dienst) }}"
          data-confirm="„{{ $dienst->name }}“ nicht mehr überwachen? Der Verlauf wird ebenfalls gelöscht."
          onsubmit="return confirm(this.dataset.confirm)">
        @csrf
        @method('DELETE')
    </form>
@endforeach

<form method="post" action="{{ route('monitoring.einstellungen.update') }}">
    @csrf

    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0"><i class="fas fa-server"></i> Überwachte Dienste</h6></div>
        <div class="card-body p-0">
            @if($monDienste->isEmpty())
                <p class="p-3 mb-0 text-muted">
                    Noch keine Dienste. Heartbeat-Dienste (z. B. <code>vpn</code>, <code>rdp</code>) erscheinen automatisch mit der ersten Meldung,
                    Webserver fügen Sie unten hinzu.
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                        <tr>
                            <th class="text-center">Aktiv</th>
                            <th>Name</th>
                            <th>Art</th>
                            <th>Adresse / Schlüssel</th>
                            <th>Abhängig von</th>
                            <th style="width: 6rem">Reihenfolge</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($monDienste as $dienst)
                            <tr>
                                <td class="text-center align-middle">
                                    <input type="hidden" name="dienste[{{ $dienst->id }}][aktiv]" value="0">
                                    <input type="checkbox" name="dienste[{{ $dienst->id }}][aktiv]" value="1" @checked(old("dienste.{$dienst->id}.aktiv", $dienst->aktiv)) aria-label="{{ $dienst->name }} aktiv">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="dienste[{{ $dienst->id }}][name]" value="{{ old("dienste.{$dienst->id}.name", $dienst->name) }}" maxlength="100" required>
                                </td>
                                <td class="align-middle small">{{ $dienst->typLabel() }}</td>
                                <td>
                                    @if($dienst->typ === 'http')
                                        <input type="url" class="form-control form-control-sm" name="dienste[{{ $dienst->id }}][url]" value="{{ old("dienste.{$dienst->id}.url", $dienst->url) }}" maxlength="500" required>
                                    @else
                                        <code>{{ $dienst->schluessel }}</code>
                                    @endif
                                </td>
                                <td>
                                    @if($dienst->istQuelle())
                                        <span class="text-muted small">–</span>
                                    @else
                                        <select class="form-control form-control-sm" name="dienste[{{ $dienst->id }}][abhaengig_von_id]">
                                            <option value="">– keiner –</option>
                                            @foreach($monDienste->where('id', '!=', $dienst->id)->where('typ', '!=', 'quelle') as $eltern)
                                                <option value="{{ $eltern->id }}" @selected((int) old("dienste.{$dienst->id}.abhaengig_von_id", $dienst->abhaengig_von_id) === $eltern->id)>{{ $eltern->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" name="dienste[{{ $dienst->id }}][sortierung]" value="{{ old("dienste.{$dienst->id}.sortierung", $dienst->sortierung) }}" min="0" max="9999">
                                </td>
                                <td class="align-middle small">
                                    <span class="badge badge-{{ $monStatusKlasse($dienst->status) }}">{{ $dienst->istQuelle() && $dienst->status === 'fehler' ? 'ausgefallen' : $monStatusText($dienst->status) }}</span>
                                    @if($dienst->meldung)
                                        <div class="text-muted">{{ $dienst->meldung }}</div>
                                    @endif
                                    @if($dienst->letzte_pruefung)
                                        <div class="text-muted">
                                            {{ $dienst->istQuelle() ? 'Letzter Heartbeat' : 'Geprüft' }} {{ $monZeit($dienst->letzte_pruefung) }}
                                            @if($dienst->latenz_ms !== null) · {{ $dienst->latenz_ms }} ms @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="align-middle text-right">
                                    <button type="submit" form="mon-loeschen-{{ $dienst->id }}" class="btn btn-sm btn-outline-danger" title="Nicht mehr überwachen" aria-label="{{ $dienst->name }} entfernen">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted px-3 py-2 mb-0">
                    „Abhängig von“: Ist der gewählte Dienst gestört, wird dieser Dienst nicht zusätzlich als Störung gemeldet
                    (z. B. Terminalserver abhängig von VPN). Heartbeat-Dienste werden nicht gemeldet, solange der Heartbeat ausbleibt.
                    Webserver gelten erst nach zwei Fehlschlägen in Folge als gestört.
                </p>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-eye"></i> Statusleiste im Dashboard sehen</h6></div>
                <div class="card-body">
                    @forelse($monRollen as $rolle)
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="mon-sichtbar-{{ $rolle->id }}" name="rollen_sichtbar[]" value="{{ $rolle->id }}"
                                   @checked(in_array($rolle->id, old('rollen_sichtbar', $monRollenSichtbar)))>
                            <label class="custom-control-label" for="mon-sichtbar-{{ $rolle->id }}">{{ $rolle->name }}</label>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Keine Rollen vorhanden.</p>
                    @endforelse
                    <small class="form-text text-muted">Ohne Auswahl sieht niemand die Statusleiste.</small>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-bell"></i> Bei Störungen benachrichtigen</h6></div>
                <div class="card-body">
                    @forelse($monRollen as $rolle)
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="mon-benachrichtigen-{{ $rolle->id }}" name="rollen_benachrichtigen[]" value="{{ $rolle->id }}"
                                   @checked(in_array($rolle->id, old('rollen_benachrichtigen', $monRollenBenachrichtigen)))>
                            <label class="custom-control-label" for="mon-benachrichtigen-{{ $rolle->id }}">{{ $rolle->name }}</label>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Keine Rollen vorhanden.</p>
                    @endforelse
                    <small class="form-text text-muted">
                        Meldung bei Störung und bei Behebung (Kategorie „System“ – Push/Mail nach den persönlichen Benachrichtigungs-Einstellungen).
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="form-group mb-0">
                <label for="mon-minuten">Heartbeat-Zeitlimit (Minuten)</label>
                <input type="number" id="mon-minuten" class="form-control" style="max-width: 10rem" name="heartbeat_minuten" min="2" max="240" value="{{ old('heartbeat_minuten', $monHeartbeatMinuten) }}" required>
                <small class="form-text text-muted">Bleibt der Heartbeat länger aus, gilt die Überwachung als ausgefallen (Empfehlung: dreifaches Sendeintervall, z. B. 15 bei alle 5 Minuten).</small>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-success btn-block mb-4">Monitoring-Einstellungen speichern</button>
</form>

<div class="row">
    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-globe"></i> Webserver hinzufügen</h6></div>
            <div class="card-body">
                <form method="post" action="{{ route('monitoring.dienste.anlegen') }}">
                    @csrf
                    <div class="form-group">
                        <label for="mon-neu-name">Name</label>
                        <input type="text" id="mon-neu-name" class="form-control" name="name" maxlength="100" placeholder="z. B. Schulhomepage" required>
                    </div>
                    <div class="form-group">
                        <label for="mon-neu-url">Adresse</label>
                        <input type="url" id="mon-neu-url" class="form-control" name="url" maxlength="500" placeholder="https://www.example.de" required>
                        <small class="form-text text-muted">
                            Wird alle 5 Minuten vom MitarbeiterBoard aufgerufen; Antworten ab HTTP 400 oder keine Antwort gelten als Fehler.
                            Webserver, die nur im Schulnetz erreichbar sind, über den Heartbeat melden.
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Hinzufügen</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-heartbeat"></i> Heartbeat (z. B. Home Assistant)</h6></div>
            <div class="card-body">
                <p class="mb-1">Endpunkt:</p>
                <pre class="bg-light border rounded p-2 user-select-all">POST {{ $monEndpunkt }}</pre>
                <p class="mb-2">
                    Token:
                    @if($monTokenEingerichtet)
                        <span class="badge badge-success">eingerichtet</span>
                        @if($monTokenErstellt)
                            <small class="text-muted">seit {{ \Illuminate\Support\Carbon::parse($monTokenErstellt)->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</small>
                        @endif
                    @else
                        <span class="badge badge-secondary">noch keins</span>
                    @endif
                </p>
                <div class="d-flex flex-wrap" style="gap: .5rem">
                    <form method="post" action="{{ route('monitoring.token') }}"
                          @if($monTokenEingerichtet) onsubmit="return confirm('Neues Token erzeugen? Das bisherige Token wird sofort ungültig.')" @endif>
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fas fa-key"></i> {{ $monTokenEingerichtet ? 'Neues Token erzeugen' : 'Token erzeugen' }}</button>
                    </form>
                    <form method="post" action="{{ route('monitoring.pruefen') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-sync-alt"></i> Jetzt prüfen</button>
                    </form>
                </div>

                <details class="mt-3">
                    <summary>Beispiel für Home Assistant</summary>
                    <p class="small text-muted mt-2 mb-1"><code>configuration.yaml</code> – Dienst-Schlüssel frei wählbar (a–z, 0–9, _ und -), neue erscheinen automatisch:</p>
<pre class="bg-light border rounded p-2 small mb-1">rest_command:
  mitarbeiterboard_heartbeat:
    url: "{{ $monEndpunkt }}"
@verbatim    method: POST
    headers:
      Authorization: !secret mitarbeiterboard_token
      Accept: application/json
    content_type: "application/json"
    timeout: 15
    payload: >
      {"dienste": {
        "vpn": {"status": "{{ 'ok' if is_state('binary_sensor.schule_vpn', 'on') else 'fehler' }}",
                "latenz_ms": {{ states('sensor.schule_vpn_latenz') | float(0) | round(0) | int }}},
        "rdp": "{{ 'ok' if is_state('binary_sensor.schule_rdp', 'on') else 'fehler' }}"
      }}@endverbatim</pre>
                    <p class="small text-muted mb-0">Dazu eine Automation, die <code>rest_command.mitarbeiterboard_heartbeat</code> alle 5 Minuten und bei Statuswechsel aufruft.</p>
                </details>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0"><i class="fas fa-history"></i> Letzte Statuswechsel</h6></div>
    <div class="card-body p-0">
        @if($monEreignisse->isEmpty())
            <p class="p-3 mb-0 text-muted">Noch keine Statuswechsel.</p>
        @else
            <table class="table table-sm mb-0">
                <tbody>
                @foreach($monEreignisse as $ereignis)
                    <tr>
                        <td class="text-nowrap small">{{ $monZeit($ereignis->created_at) }}</td>
                        <td>{{ $ereignis->dienst?->name }}</td>
                        <td class="small">
                            <span class="badge badge-{{ $monStatusKlasse($ereignis->status_alt) }}">{{ $monStatusText($ereignis->status_alt) }}</span>
                            →
                            <span class="badge badge-{{ $monStatusKlasse($ereignis->status_neu) }}">{{ $monStatusText($ereignis->status_neu) }}</span>
                        </td>
                        <td class="small text-muted">{{ $ereignis->meldung }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
