@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <form method="post" action="{{ url('user-deletion/'.$user->id.($stage === 2 ? '/anonymize' : '/purge')) }}">
            @csrf
            <div class="card">
                <div class="card-header border-bottom">
                    <h5 class="card-title">
                        @if($stage === 2)
                            Stufe 2: {{ $user->name }} anonymisieren
                        @else
                            Stufe 3: Konto #{{ $user->id }} endgültig löschen
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    @if($stage === 2)
                        <p>
                            Kontodaten werden überschrieben, persönliche Daten gelöscht und gemeinsame Inhalte der
                            „{{ config('user_deletion.placeholder_name') }}“ zugeordnet. Aufbewahrungspflichtige Personaldaten
                            bleiben gesperrt bis zum jeweiligen Fristende. <strong>Dieser Schritt kann nicht rückgängig gemacht werden.</strong>
                        </p>
                        @unless($allowed)
                            <div class="alert alert-warning">
                                Die Sperrfrist läuft noch bis {{ $anonymizeDueAt->format('d.m.Y') }}.
                                Eine vorzeitige Anonymisierung ist nur bei einem Löschantrag nach Art. 17 DSGVO zulässig.
                            </div>
                        @endunless
                    @else
                        <p>
                            Alle Daten, deren Aufbewahrungsfrist abgelaufen ist, werden endgültig gelöscht, auch Dateien in Nextcloud.
                            Ist danach nichts mehr aufzubewahren, wird auch der Benutzerdatensatz entfernt.
                            <strong>Dieser Schritt kann nicht rückgängig gemacht werden.</strong>
                        </p>
                        @if($user->legal_hold)
                            <div class="alert alert-danger">Aufbewahrungssperre: {{ $user->legal_hold_reason }}</div>
                        @elseif(!$allowed)
                            <div class="alert alert-warning">Derzeit ist keine Aufbewahrungsfrist abgelaufen.</div>
                        @endif
                    @endif

                    <h6 class="mt-4">Vollständige Übersicht der Auswirkungen</h6>
                    @include('users.deletion._report')

                    @if($stage === 2 && !$allowed)
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="early" value="1" id="early">
                            <label class="form-check-label" for="early">Vorzeitig wegen Löschantrag nach Art. 17 DSGVO</label>
                        </div>
                        <div class="form-group mt-2">
                            <input type="text" name="early_reason" class="form-control" placeholder="Datum und Eingang des Antrags">
                        </div>
                    @endif

                    @if($allowed || $stage === 2)
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm" required>
                            <label class="form-check-label" for="confirm">Ich habe die Auswirkungen geprüft und bestätige die Ausführung.</label>
                        </div>
                    @endif
                </div>
                <div class="card-footer">
                    <a href="{{ url('user-deletion') }}" class="btn btn-secondary">Abbrechen</a>
                    @if($allowed || $stage === 2)
                        <button type="submit" class="btn btn-danger">
                            {{ $stage === 2 ? 'Anonymisieren' : 'Endgültig löschen' }}
                        </button>
                    @endif
                </div>
            </div>
        </form>
    </div>
@endsection
