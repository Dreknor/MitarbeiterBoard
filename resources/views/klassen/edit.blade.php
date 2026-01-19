@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-10 mx-auto">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>
                            Klasse bearbeiten
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{url('klassen/'.$klasse->id)}}" method="post" class="form-horizontal">
                            @csrf
                            @method("put")
                            <div class="form-row">
                                <div class="col-md-4 col-sm-12 mb-3">
                                    <label for="name">Name der Klasse</label>
                                    <input name="name" id="name" class="form-control" value="{{$klasse->name}}" required>
                                </div>
                                <div class="col-md-4 col-sm-8 mb-3">
                                    <label for="kuerzel">Kürzel</label>
                                    <input name="kuerzel" id="kuerzel" class="form-control" value="{{$klasse->kuerzel}}" required>
                                </div>
                                <div class="col-md-4 col-sm-8 mb-3">
                                    <div class="form-group">
                                        <label for="color">Farbe</label>
                                        <input type="color" class="form-control color-picker-enhanced" placeholder="#ffffff" name="color" value="{{$klasse->color}}">

                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-12 mb-3">
                                    <label for="paed_user_ids">Mitarbeiter (pädagogisches Tagebuch)</label>
                                    <select name="paed_user_ids[]" id="paed_user_ids" class="form-control" multiple size="5">
                                        @foreach($paedUsers as $u)
                                            <option value="{{$u->id}}" @if($klasse->paed_users->contains($u->id)) selected @endif>{{$u->name}}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted d-block">Nur ausgewählte Nutzer (und Nutzer aus gewählten Gruppen) sehen/bearbeiten dieses Klassentagebuch.</small>
                                </div>
                                <div class="col-md-4 col-sm-12 mb-3">
                                    <label for="paed_group_ids">Gruppen (fügen deren Mitglieder hinzu)</label>
                                    <select name="paed_group_ids[]" id="paed_group_ids" class="form-control" multiple size="5">
                                        @isset($groups)
                                            @foreach($groups as $g)
                                                <option value="{{$g->id}}" @php
                                                    // preselect wenn ALLE Benutzer der Gruppe bereits explizit zugewiesen sind? -> nicht eindeutig rekonstruierbar
                                                @endphp>{{ $g->name }}</option>
                                            @endforeach
                                        @endisset
                                    </select>
                                    <small class="text-muted">Mitglieder der ausgewählten Gruppen (mit Berechtigung "view paed diary") werden automatisch ergänzt. Bereits vorhandene Einzelzuweisungen bleiben bestehen.</small>
                                </div>
                                <div class="col-md-4 col-sm-12 mb-3">
                                    <label for="grading_system_id">Graduierungssystem</label>
                                    <select name="grading_system_id" id="grading_system_id" class="form-control @error('grading_system_id') is-invalid @enderror">
                                        <option value="">-- kein System --</option>
                                        @foreach($systems as $s)
                                            <option value="{{ $s->id }}" @if(old('grading_system_id', $klasse->grading_system_id) == $s->id) selected @endif>{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('grading_system_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Wenn ein System gewählt wird, können Klassenstufen verwendet werden.</small>
                                </div>
                            </div>
                            <div class="form-row">
                                 <div class="col-md-4 col-sm-12">
                                     <button type="submit" class="btn btn-primary mt-3">Speichern</button>
                                 </div>
                             </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Schüler der Klasse ({{$klasse->schueler->count()}})</h5>
                    </div>
                    <div class="card-body">
                        @if($klasse->schueler->count() == 0)
                            <p class="text-muted">Noch keine Schüler eingetragen.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                    <tr>
                                        <th>Vorname</th>
                                        <th>Nachname</th>
                                        <th>Geburtsdatum</th>
                                        <th class="text-right">Aktion</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($klasse->schueler as $schueler)
                                        <tr>
                                            <td>{{$schueler->vorname}}</td>
                                            <td>{{$schueler->nachname}}</td>
                                            <td>{{$schueler->geburtsdatum?->format('d.m.Y')}}</td>
                                            <td class="text-right">
                                                <a href="{{route('schueler.edit', $schueler)}}" class="btn btn-primary btn-sm">edit</a>
                                                <form action="{{route('schueler.destroy', $schueler)}}" method="post" class="d-inline" onsubmit="return confirm('Schüler wirklich löschen?');">
                                                    @csrf
                                                    @method('delete')
                                                    <button class="btn btn-danger btn-sm">löschen</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card mb-5">
                    <div class="card-header">
                        <h5 class="mb-0">Neuen Schüler hinzufügen</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{route('schueler.store', $klasse)}}" method="post">
                            @csrf
                            <div class="form-row">
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <label for="vorname">Vorname</label>
                                    <input type="text" name="vorname" id="vorname" class="form-control" required>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <label for="nachname">Nachname</label>
                                    <input type="text" name="nachname" id="nachname" class="form-control" required>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <label for="geburtsdatum">Geburtsdatum</label>
                                    <input type="date" name="geburtsdatum" id="geburtsdatum" class="form-control">
                                </div>
                                <div class="col-md-3 col-sm-6 mb-2 d-flex align-items-end">
                                    <button class="btn btn-success w-100" type="submit">Hinzufügen</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
