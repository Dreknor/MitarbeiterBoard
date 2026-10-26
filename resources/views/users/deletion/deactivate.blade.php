@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <form method="post" action="{{ url('users/'.$user->id.'/deactivate') }}">
            @csrf
            <div class="card">
                <div class="card-header border-bottom">
                    <h5 class="card-title">{{ $user->name }} deaktivieren</h5>
                </div>
                <div class="card-body">
                    <p>
                        Das Konto wird gesperrt und kann bis zur Anonymisierung reaktiviert werden.
                        Die Anonymisierung ist {{ config('user_deletion.anonymize_after_months') }} Monate nach dem Austritt möglich
                        und muss von einem Admin bestätigt werden.
                    </p>

                    <div class="form-group">
                        <label for="left_at">Austrittsdatum (Beginn aller Löschfristen)</label>
                        <input type="date" class="form-control" id="left_at" name="left_at" style="max-width: 220px"
                               value="{{ old('left_at', now()->toDateString()) }}" required>
                    </div>

                    <h6 class="mt-4">Offene Verantwortungen übergeben</h6>
                    @if(empty($items))
                        <p class="text-muted">Keine offenen Verantwortungen gefunden.</p>
                    @else
                        <p class="text-muted">
                            Vorgeschlagen ist die oder der Vorgesetzte, zur Auswahl stehen außerdem die Mitglieder der gleichen Gruppen.
                            Ohne Auswahl werden die Einträge in der Anonymisierung der „{{ config('user_deletion.placeholder_name') }}“ zugeordnet.
                        </p>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Verantwortung</th>
                                    <th class="text-right" style="width: 110px">Einträge</th>
                                    <th style="width: 320px">Übergeben an</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                    <tr>
                                        <td>{{ $item['handover']->label }}</td>
                                        <td class="text-right">{{ $item['count'] }}</td>
                                        <td>
                                            <select name="handover[{{ $item['handover']->key }}]" class="form-control form-control-sm">
                                                <option value="">nicht übergeben</option>
                                                @foreach($candidates as $candidate)
                                                    <option value="{{ $candidate->id }}" @selected(old('handover.'.$item['handover']->key, $suggested?->id) == $candidate->id)>
                                                        {{ $candidate->name }}@if($suggested && $candidate->id === $suggested->id) (Vorgesetzte/r)@endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    <h6 class="mt-4">Auswirkungen</h6>
                    @include('users.deletion._report')

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm" required>
                        <label class="form-check-label" for="confirm">Ich habe die Übergabe und die Auswirkungen geprüft.</label>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="{{ url('users/'.$user->id) }}" class="btn btn-secondary">Abbrechen</a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-user-slash"></i> Benutzer deaktivieren
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
