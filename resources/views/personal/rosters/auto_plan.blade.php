@extends('layouts.app')

@section('title') Auto-Umplanung Vorschläge @endsection
@section('site-title') Auto-Umplanung – Vorschläge @endsection

@section('content')
    <div class="container-fluid">
        <style>
            /* Sichere, sichtbare Checkboxen ohne Bootstrap-Abhängigkeit */
            .plain-checkbox{appearance:auto !important;-webkit-appearance:checkbox; width:16px; height:16px; margin:0 6px 0 0; position:static!important; opacity:1!important; cursor:pointer;}
            label.checkbox-compact{display:flex; align-items:center; gap:.25rem; font-size:.8rem; line-height:1.1rem; margin-bottom:.25rem; cursor:pointer;}
            label.checkbox-compact span{flex:1 1 auto;}
            .day-matrix-wrapper{max-height:260px; overflow:auto; border:1px solid #ddd; border-radius:.25rem; background:#fff;}
            table.day-matrix{width:100%; border-collapse:separate; border-spacing:0; font-size:.75rem;}
            table.day-matrix th, table.day-matrix td{padding:.35rem .4rem; border-bottom:1px solid #eef; vertical-align:middle; white-space:nowrap;}
            table.day-matrix thead th{position:sticky; top:0; background:#f8f9fc; z-index:5; font-weight:600; font-size:.68rem; text-align:center;}
            table.day-matrix tbody tr:last-child td{border-bottom:none;}
            .day-col-header small{display:block; font-size:.6rem; font-weight:400; color:#666;}
            .matrix-sticky-left{position:sticky; left:0; background:#fff; z-index:4; box-shadow:1px 0 0 #e0e6ef;}
            .legend-note{font-size:.7rem; color:#666;}
            .toggle-col-btn{font-size:.55rem; letter-spacing:.5px; margin-top:2px; display:inline-block;}
            .bg-day-selected{background:#f0faff;} .day-matrix td.day-selected{background:#e8f6ff;}
        </style>
        <div class="card mb-2">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Vorschläge für Dienstplan #{{$roster->id}} (Woche ab {{$roster->start_date->format('d.m.Y')}})</span>
                <div class="d-flex gap-2 align-items-center">
                    @if(isset($hasUndo) && $hasUndo)
                        <a href="{{route('roster.autoPlan.undo',$roster->id)}}" class="btn btn-sm btn-warning" onclick="return confirm('Letzte Auto-Umplanung wirklich rückgängig machen?')">Undo</a>
                    @endif
                    <a href="{{route('roster.show',$roster->id)}}" class="btn btn-sm btn-secondary">Zurück</a>
                </div>
            </div>
            <!-- Anforderungen Verwaltung -->
            <div class="card-body border-bottom pb-1">
                <h6 class="fw-bold small mb-2">Aufgaben-Anforderungen</h6>
                <div class="row g-3">
                    <div class="col-12 col-lg-5">
                        <form method="post" action="{{route('roster.taskRequirements.store',$roster->id)}}" class="border rounded p-2 bg-light">
                            @csrf
                            <div class="small fw-bold mb-1">Neue Anforderung</div>
                            <div class="mb-1">
                                <label class="form-label small mb-0">Event-Name *</label>
                                <input type="text" name="event_name" class="form-control form-control-sm" required maxlength="120">
                            </div>
                            <div class="d-flex gap-1 mb-1">
                                <div style="flex:1">
                                    <label class="form-label small mb-0">Erforderlicher Start</label>
                                    <input type="time" name="required_start" class="form-control form-control-sm">
                                </div>
                                <div style="flex:1">
                                    <label class="form-label small mb-0">Erforderliches Ende</label>
                                    <input type="time" name="required_end" class="form-control form-control-sm">
                                </div>
                            </div>
                            <label class="checkbox-compact mb-2 mt-1">
                                <input type="checkbox" class="plain-checkbox" name="adjust_working_time" value="1">
                                <span>Arbeitszeit darf angepasst werden</span>
                            </label>
                            <div class="d-flex justify-content-end">
                                <button class="btn btn-primary btn-sm">Speichern</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-12 col-lg-7">
                        <div class="small fw-bold mb-1">Vorhandene Anforderungen</div>
                        <div class="table-responsive" style="max-height:210px; overflow:auto;">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Event</th>
                                    <th>Start</th>
                                    <th>Ende</th>
                                    <th style="width:40px" title="Arbeitszeit darf angepasst werden">AZ*</th>
                                    <th style="width:120px"></th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($requirements as $req)
                                    <tr data-req-row="{{$req->id}}" class="req-row">
                                        <td>
                                            <span class="req-view">{{$req->event_name}}</span>
                                            <form method="post" action="{{route('roster.taskRequirements.update',$req)}}" class="d-none req-edit">
                                                @csrf @method('PUT')
                                                <input type="text" name="event_name" class="form-control form-control-sm" value="{{$req->event_name}}" required maxlength="120">
                                            </form>
                                        </td>
                                        <td>
                                            <span class="req-view">{{$req->required_start?->format('H:i') ?? '—'}}</span>
                                            <form method="post" action="{{route('roster.taskRequirements.update',$req)}}" class="d-none req-edit">
                                                @csrf @method('PUT')
                                                <input type="time" name="required_start" class="form-control form-control-sm" value="{{$req->required_start?->format('H:i')}}">
                                            </form>
                                        </td>
                                        <td>
                                            <span class="req-view">{{$req->required_end?->format('H:i') ?? '—'}}</span>
                                            <form method="post" action="{{route('roster.taskRequirements.update',$req)}}" class="d-none req-edit">
                                                @csrf @method('PUT')
                                                <input type="time" name="required_end" class="form-control form-control-sm" value="{{$req->required_end?->format('H:i')}}">
                                            </form>
                                        </td>
                                        <td class="text-center">
                                            <span class="req-view">@if($req->adjust_working_time) <span class="text-warning" title="Arbeitszeit Anpassung erlaubt">★</span>@else — @endif</span>
                                            <form method="post" action="{{route('roster.taskRequirements.update',$req)}}" class="d-none req-edit">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="event_name" value="{{$req->event_name}}"><!-- preserve -->
                                                <input type="hidden" name="required_start" value="{{$req->required_start?->format('H:i')}}">
                                                <input type="hidden" name="required_end" value="{{$req->required_end?->format('H:i')}}">
                                                <input type="checkbox" class="plain-checkbox" name="adjust_working_time" value="1" @checked($req->adjust_working_time)>
                                            </form>
                                        </td>
                                        <td class="text-end">
                                            <div class="req-view d-flex gap-1 justify-content-end">
                                                <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-1 js-edit-req" data-id="{{$req->id}}">Edit</button>
                                                <form method="post" action="{{route('roster.taskRequirements.destroy',$req)}}" onsubmit="return confirm('Anforderung löschen?')">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-outline-danger btn-xs py-0 px-1">Del</button>
                                                </form>
                                            </div>
                                            <div class="req-edit d-none d-flex gap-1 justify-content-end">
                                                <button type="button" class="btn btn-success btn-xs py-0 px-1 js-save-req" data-id="{{$req->id}}">OK</button>
                                                <button type="button" class="btn btn-secondary btn-xs py-0 px-1 js-cancel-req" data-id="{{$req->id}}">X</button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted small">Keine Anforderungen vorhanden</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="small mt-1 text-muted">Legende: AZ* = Arbeitszeit darf angepasst werden (★)</div>
                    </div>
                </div>
            </div>
            <!-- Ende Anforderungen Verwaltung -->
            <div class="card-body border-bottom">
                <form method="get" action="{{route('roster.autoPlan',$roster->id)}}" class="row g-2 align-items-start">
                    <div class="col-12 col-lg-4">
                        <label class="small fw-bold mb-1">Mitarbeitende (ganze Woche simuliert abwesend)</label>
                        <div class="border rounded p-2 overflow-auto" style="max-height:180px">
                            @foreach($employes as $e)
                                <label class="checkbox-compact">
                                    <input type="checkbox" class="plain-checkbox js-global-sim" name="simulate_absent[]" value="{{$e->id}}" @checked(in_array($e->id,$simulate ?? [])) data-emp="{{$e->id}}">
                                    <span>{{$e->vorname}} {{$e->familienname}}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12 col-lg-8">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="small fw-bold m-0">Tageweise Simulation (hat Vorrang vor globaler Auswahl)</label>
                            <div class="legend-note">Tipp: Tages-Häkchen deaktiviert automatisch die globale Auswahl des Mitarbeiters.</div>
                        </div>
                        <div class="day-matrix-wrapper mb-2">
                            <table class="day-matrix">
                                <thead>
                                <tr>
                                    <th class="matrix-sticky-left" style="min-width:140px; text-align:left;">Mitarbeiter</th>
                                    @foreach($days as $d)
                                        @php($dKey = $d->format('Y-m-d'))
                                        <th class="day-col-header" data-day-col="{{$dKey}}">
                                            {{$d->locale('de')->isoFormat('dd')}}
                                            <small>{{$d->format('d.m.')}}</small>
                                            <button type="button" class="btn btn-light border-0 p-0 toggle-col-btn js-toggle-day" data-day="{{$dKey}}">alle</button>
                                        </th>
                                    @endforeach
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($employes as $e)
                                    @php($eid=$e->id)
                                    <tr data-row-emp="{{$eid}}">
                                        <td class="matrix-sticky-left" style="background:#fff; font-size:.72rem;">
                                            <strong>{{$e->vorname}}</strong> <span class="text-muted">{{$e->familienname}}</span>
                                            <div class="mt-1">
                                                <button class="btn btn-outline-secondary btn-xs py-0 px-1 toggle-col-btn js-row-all" type="button" data-emp="{{$eid}}">alle</button>
                                                <button class="btn btn-outline-secondary btn-xs py-0 px-1 toggle-col-btn js-row-none" type="button" data-emp="{{$eid}}">keine</button>
                                            </div>
                                        </td>
                                        @foreach($days as $d)
                                            @php($dKey=$d->format('Y-m-d'))
                                            @php($checked = in_array($eid, $simulate_per_day[$dKey] ?? []))
                                            <td class="text-center @if($checked) day-selected @endif" data-cell-day="{{$dKey}}" data-cell-emp="{{$eid}}">
                                                <input type="checkbox" class="plain-checkbox js-day-sim" name="simulate_absent_day[{{$dKey}}][]" value="{{$eid}}" @checked($checked) data-day="{{$dKey}}" data-emp="{{$eid}}">
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <button class="btn btn-sm btn-outline-secondary js-clear-days" type="button">Alle Tages-Häkchen entfernen</button>
                            <button class="btn btn-sm btn-outline-secondary js-clear-globals" type="button">Globale Auswahl leeren</button>
                            <button class="btn btn-sm btn-outline-secondary js-select-all-days" type="button">Alles (Tage) markieren</button>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Neu berechnen mit Auswahl</button>
                    </div>
                </form>
            </div>
            @if(isset($summary))
                <div class="card-body border-bottom py-2">
                    <div class="row text-center small">
                        <div class="col">Betroffene Events<br><strong>{{$summary['betroffene_events']}}</strong></div>
                        <div class="col">Neu zugewiesen<br><strong>{{$summary['neu_zugewiesen']}}</strong></div>
                        <div class="col">Nicht zuweisbar<br><strong>{{$summary['nicht_zuweisbar']}}</strong></div>
                        <div class="col">Zusatz-Minuten<br><strong>{{$summary['zusatz_minuten']}}</strong></div>
                        <div class="col">Neue Pausen<br><strong>{{$summary['neue_pausen']}}</strong></div>
                    </div>
                </div>
            @endif
            <div class="card-body p-0">
                @if(count($suggestions)==0)
                    <div class="p-3">Keine Änderungen notwendig – keine relevanten Events gefunden.</div>
                @else
                    <form method="post" action="{{route('roster.autoPlan.apply',$roster->id)}}" id="applyForm">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0 align-middle">
                                <thead class="table-light">
                                <tr>
                                    <th style="width:38px"><input type="checkbox" id="checkAll" class="plain-checkbox" title="Alle wählen"></th>
                                    <th>Datum</th>
                                    <th>Event</th>
                                    <th>Anforderung</th>
                                    <th>Von</th>
                                    <th>Zu</th>
                                    <th>Aktion</th>
                                    <th>Grund</th>
                                    <th>Arbeitszeit-Anpassung</th>
                                    <th>Pausen-Vorschlag</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($suggestions as $s)
                                    @php($req = $s['requirement'] ?? null)
                                    <tr class="@if(($s['is_new'] ?? false)) table-success @elseif(($s['is_changed'] ?? false)) table-warning @endif">
                                        <td><input type="checkbox" name="selected[]" value="{{$s['index']}}" class="row-select plain-checkbox"></td>
                                        <td>{{$s['date']}}</td>
                                        <td title="ID: {{$s['event_id']}}">
                                            {{$s['event_name'] ?? $s['event_id']}}
                                            @if($s['is_new'] ?? false)<span class="badge bg-success ms-1" title="Neu seit letzter Berechnung">Neu</span>@elseif($s['is_changed'] ?? false)<span class="badge bg-warning text-dark ms-1" title="Inhalt hat sich seit letzter Berechnung geändert">Geändert</span>@endif
                                        </td>
                                        <td class="small">
                                            @if($req)
                                                <span title="Funktion {{$req['function']}}{{$req['start']? ' Start '.$req['start']:''}}{{$req['end']? ' Ende '.$req['end']:''}} @if($req['adjust']) | Arbeitszeit darf angepasst werden @endif">
                                                    @if($req['adjust'])<span class="text-warning" title="Arbeitszeit darf angepasst werden">★</span>@endif
                                                    {{$req['function']}} {{$req['start'] ?? '—'}}-{{$req['end'] ?? '—'}}
                                                    @if(($req['adjusted'] ?? false)) <span class="text-primary" title="Anforderung erforderte Anpassung">⚙</span>@endif
                                                </span>
                                            @else — @endif
                                        </td>
                                        <td>{{$s['from']['name'] ?? '—'}}</td>
                                        <td>{{$s['to']['name'] ?? '—'}}</td>
                                        <td>{{$s['action']}}</td>
                                        <td class="small">{{$s['reason']}}</td>
                                        <td class="small">
                                            @if($s['adjust_working_time'])
                                                <span class="text-primary" title="Arbeitszeit wurde für diese Zuweisung angepasst">⚙</span>
                                                WT {{$s['adjust_working_time']['working_time_id']}}:
                                                @if($s['adjust_working_time']['new_start']) Start→{{$s['adjust_working_time']['new_start']}} @endif
                                                @if($s['adjust_working_time']['new_end']) Ende→{{$s['adjust_working_time']['new_end']}} @endif
                                                (+{{$s['adjust_working_time']['added_minutes']}} Min)
                                            @else — @endif
                                        </td>
                                        <td class="small">
                                            @if($s['add_break'])
                                                <label class="checkbox-compact" style="margin:0;">
                                                    <input type="checkbox" class="plain-checkbox" name="break_selected[]" value="{{$s['index']}}">
                                                    <span>{{$s['add_break']['start']}}-{{$s['add_break']['end']}}</span>
                                                </label>
                                            @else — @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($hasDiff ?? false)
                            <div class="px-3 py-2 small text-muted border-top">
                                Legende: <span class="badge bg-success">Neu</span> = neuer Vorschlag seit letzter Berechnung, <span class="badge bg-warning text-dark">Geändert</span> = Inhalt geändert.
                            </div>
                        @endif
                        <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="small text-muted">Nur markierte Vorschläge werden übernommen. Pausen nur wenn zusätzlich markiert.</div>
                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Ausgewählte Vorschläge anwenden?')">Ausgewählte anwenden</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('js')
<script>
    // Checkbox Select All Vorschläge
    document.getElementById('checkAll')?.addEventListener('change', function(){
        document.querySelectorAll('.row-select').forEach(cb=>cb.checked=this.checked);
    });

    // Tagesweise Matrix Funktionen
    const dayColToggles = document.querySelectorAll('.js-toggle-day');
    dayColToggles.forEach(btn=>{
        btn.addEventListener('click',()=>{
            const day = btn.dataset.day;
            const cells = document.querySelectorAll('td[data-cell-day="'+day+'"] input.js-day-sim');
            const allChecked = Array.from(cells).every(c=>c.checked);
            cells.forEach(c=>{c.checked=!allChecked; c.dispatchEvent(new Event('change'));});
        });
    });

    // Zeilenweise alle / keine
    document.querySelectorAll('.js-row-all').forEach(b=> b.addEventListener('click',()=>{
        const emp=b.dataset.emp; const boxes=document.querySelectorAll('input.js-day-sim[data-emp="'+emp+'"]');
        boxes.forEach(cb=>{if(!cb.checked){cb.checked=true; cb.dispatchEvent(new Event('change'));}});
    }));
    document.querySelectorAll('.js-row-none').forEach(b=> b.addEventListener('click',()=>{
        const emp=b.dataset.emp; const boxes=document.querySelectorAll('input.js-day-sim[data-emp="'+emp+'"]');
        boxes.forEach(cb=>{if(cb.checked){cb.checked=false; cb.dispatchEvent(new Event('change'));}});
    }));

    // Globale Auswahl leeren
    document.querySelector('.js-clear-globals')?.addEventListener('click',()=>{
        document.querySelectorAll('.js-global-sim').forEach(cb=>cb.checked=false);
    });
    // Tages-Häkchen leeren
    document.querySelector('.js-clear-days')?.addEventListener('click',()=>{
        document.querySelectorAll('.js-day-sim').forEach(cb=>{cb.checked=false; cb.closest('td')?.classList.remove('day-selected');});
    });
    // Alle Tage markieren
    document.querySelector('.js-select-all-days')?.addEventListener('click',()=>{
        document.querySelectorAll('.js-day-sim').forEach(cb=>{ if(!cb.checked){cb.checked=true; cb.dispatchEvent(new Event('change'));}});
    });

    // Wenn Tages-Checkbox gesetzt wird: globale für diesen Mitarbeiter entfernen (Priorität sichtbar machen)
    document.querySelectorAll('.js-day-sim').forEach(cb=>{
        cb.addEventListener('change',()=>{
            cb.closest('td')?.classList.toggle('day-selected', cb.checked);
            if(cb.checked){
                const emp = cb.dataset.emp;
                const globalBox = document.querySelector('.js-global-sim[value="'+emp+'"]');
                if(globalBox){ globalBox.checked=false; }
            }
        });
    });

    // Inline Edit Anforderungen
    document.querySelectorAll('.js-edit-req').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id; const row = document.querySelector('[data-req-row="'+id+'"]');
            row.querySelectorAll('.req-view').forEach(el=>el.classList.add('d-none'));
            row.querySelectorAll('.req-edit').forEach(el=>el.classList.remove('d-none'));
        });
    });
    document.querySelectorAll('.js-cancel-req').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id; const row = document.querySelector('[data-req-row="'+id+'"]');
            row.querySelectorAll('.req-view').forEach(el=>el.classList.remove('d-none'));
            row.querySelectorAll('.req-edit').forEach(el=>el.classList.add('d-none'));
        });
    });
    document.querySelectorAll('.js-save-req').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id; const row = document.querySelector('[data-req-row="'+id+'"]');
            // Sammle die drei Edit-Formulare und mergen in ein FormData -> abschicken (Ajax optional; hier klassisch nacheinander)
            // Vereinfachung: wir submitten die erste Edit-Form und hängen Werte der anderen an
            const forms = row.querySelectorAll('form.req-edit');
            if(forms.length){
                const main = forms[0];
                // Kopiere Werte aus weiteren Formularen als versteckte Felder
                for(let i=1;i<forms.length;i++){
                    forms[i].querySelectorAll('input,checkbox,select,textarea').forEach(inp => {
                        if(inp.type==='checkbox'){ if(inp.checked){ let hidden=document.createElement('input'); hidden.type='hidden'; hidden.name=inp.name; hidden.value=inp.value; main.appendChild(hidden); } }
                        else { let hidden=document.createElement('input'); hidden.type='hidden'; hidden.name=inp.name; hidden.value=inp.value; main.appendChild(hidden); }
                    });
                }
                main.submit();
            }
        });
    });
</script>
@endpush
