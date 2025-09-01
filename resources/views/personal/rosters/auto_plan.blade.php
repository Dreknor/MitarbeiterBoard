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
            <div class="card-body border-bottom">
                <form method="get" action="{{route('roster.autoPlan',$roster->id)}}" class="row g-2 align-items-end">
                    <div class="col-12 col-md-6">
                        <label class="small fw-bold mb-1">Mitarbeitende simuliert abwesend</label>
                        <div class="border rounded p-2 overflow-auto" style="max-height:180px">
                            @foreach($employes as $e)
                                <label class="checkbox-compact">
                                    <input type="checkbox" class="plain-checkbox" name="simulate_absent[]" value="{{$e->id}}" @checked(in_array($e->id,$simulate ?? []))>
                                    <span>{{$e->vorname}} {{$e->familienname}}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <button type="submit" class="btn btn-primary w-100 mt-2 mt-md-0">Neu berechnen</button>
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
                                    <tr>
                                        <td><input type="checkbox" name="selected[]" value="{{$s['index']}}" class="row-select plain-checkbox"></td>
                                        <td>{{$s['date']}}</td>
                                        <td title="ID: {{$s['event_id']}}">{{$s['event_name'] ?? $s['event_id']}}</td>
                                        <td>{{$s['from']['name'] ?? '—'}}</td>
                                        <td>{{$s['to']['name'] ?? '—'}}</td>
                                        <td>{{$s['action']}}</td>
                                        <td class="small">{{$s['reason']}}</td>
                                        <td class="small">
                                            @if($s['adjust_working_time'])
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

@push('scripts')
<script>
    document.getElementById('checkAll')?.addEventListener('change', function(){
        document.querySelectorAll('.row-select').forEach(cb=>cb.checked=this.checked);
    });
</script>
@endpush
