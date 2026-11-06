@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title">Ausgeschiedene Mitarbeiter: deaktivierte Konten (Stufe 1)</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Anonymisierung frühestens {{ config('user_deletion.anonymize_after_months') }} Monate nach Austritt, nur nach Bestätigung.
                    Konten, die vor Einführung des Löschkonzepts deaktiviert wurden, sind als Altkonto markiert und brauchen eine Entscheidung.
                </p>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Austritt</th>
                            <th>Anonymisierung ab</th>
                            <th>Status</th>
                            @if($canManage)<th></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deactivated as $user)
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $retention->leftAt($user)->format('d.m.Y') }}</td>
                                <td>{{ $retention->anonymizeDueAt($user)->format('d.m.Y') }}</td>
                                <td>
                                    @if($service->isKeptByReview($user))
                                        <span class="badge badge-secondary">vorerst behalten</span>
                                        <small class="d-block text-muted">{{ $user->deletion_review_note }}</small>
                                    @elseif($service->canAnonymize($user))
                                        <span class="badge badge-warning">Anonymisierung fällig</span>
                                    @elseif((int) $user->deletion_notified_stage === 0)
                                        <span class="badge badge-info">Altkonto, Entscheidung offen</span>
                                    @else
                                        <span class="badge badge-light">Sperrfrist läuft</span>
                                    @endif
                                </td>
                                @if($canManage)
                                    <td class="text-nowrap">
                                        <a href="{{ url('users/restore/'.$user->id) }}" class="btn btn-sm btn-outline-secondary">Reaktivieren</a>
                                        <a href="{{ url('user-deletion/'.$user->id.'/stage/2') }}" class="btn btn-sm btn-outline-danger">Anonymisieren …</a>
                                        <form method="post" action="{{ url('user-deletion/'.$user->id.'/keep') }}" class="form-inline d-inline-flex mt-1">
                                            @csrf
                                            <input type="text" name="note" class="form-control form-control-sm mr-1" placeholder="Begründung" required>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Vorerst behalten</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">Keine deaktivierten Konten.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title">Personalarchiv: anonymisierte Konten (Stufe 2)</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Aufbewahrungspflichtige Personaldaten sind gesperrt und werden nach Fristende endgültig gelöscht:
                    Lohn- und Entgeltunterlagen {{ config('user_deletion.retention.payroll') }} Jahre,
                    Qualifikationen, Fortbildungen und Einwilligungen {{ config('user_deletion.retention.claims') }} Jahre,
                    jeweils ab Ende des Austrittsjahres.
                </p>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Austritt</th>
                            <th>Aufbewahrte Datensätze</th>
                            <th>Lohnunterlagen bis</th>
                            <th>Status</th>
                            @if($canManage)<th></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($anonymized as $user)
                            @php($retained = $service->remainingRetainedCount($user))
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td>{{ $retention->leftAt($user)->format('d.m.Y') }}</td>
                                <td>
                                    {{ $retained }}
                                    @if($retained > 0)
                                        <a data-toggle="collapse" href="#archive-{{ $user->id }}" class="small ml-1">Details</a>
                                    @endif
                                </td>
                                <td>{{ $retention->expiresAt($user, 'payroll')->subDay()->format('d.m.Y') }}</td>
                                <td>
                                    @if($user->legal_hold)
                                        <span class="badge badge-danger">Aufbewahrungssperre</span>
                                        <small class="d-block text-muted">{{ $user->legal_hold_reason }}</small>
                                    @elseif($service->canPurge($user))
                                        <span class="badge badge-warning">Löschung fällig</span>
                                    @else
                                        <span class="badge badge-light">gesperrt aufbewahrt</span>
                                    @endif
                                </td>
                                @if($canManage)
                                    <td class="text-nowrap">
                                        @if(!$user->legal_hold && $service->canPurge($user))
                                            <a href="{{ url('user-deletion/'.$user->id.'/stage/3') }}" class="btn btn-sm btn-outline-danger">Endgültig löschen …</a>
                                        @endif
                                        <form method="post" action="{{ url('user-deletion/'.$user->id.'/legal-hold') }}" class="form-inline d-inline-flex mt-1">
                                            @csrf
                                            @if($user->legal_hold)
                                                <input type="hidden" name="hold" value="0">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Sperre aufheben</button>
                                            @else
                                                <input type="hidden" name="hold" value="1">
                                                <input type="text" name="reason" class="form-control form-control-sm mr-1" placeholder="Grund" required>
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Aufbewahrungssperre</button>
                                            @endif
                                        </form>
                                    </td>
                                @endif
                            </tr>
                            @if($retained > 0)
                                <tr class="collapse" id="archive-{{ $user->id }}">
                                    <td colspan="6">
                                        @include('users.deletion._report', ['report' => $service->impactReport($user, 3), 'externalSteps' => []])
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="6" class="text-muted">Keine anonymisierten Konten.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
