@extends('layouts.app')

@section('title')
    Urlaubsverwaltung - Genehmigte Urlaube verwalten
@endsection

@section('site-title')
    Urlaubsverwaltung - Genehmigte Urlaube verwalten
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ url('holidays') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-tasks"></i> Genehmigte Urlaube verwalten
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Filterbereich -->
                        <form method="GET" action="{{ url('holidays/manage') }}" class="mb-4">
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="user_id">
                                            <i class="fas fa-user"></i> Mitarbeiter filtern
                                        </label>
                                        <select name="user_id" id="user_id" class="form-control">
                                            <option value="">-- Alle Mitarbeiter --</option>
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}" {{ $selectedUserId == $user->id ? 'selected' : '' }}>
                                                    {{ $user->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="future_only">
                                            <i class="fas fa-calendar-alt"></i> Zeitraum
                                        </label>
                                        <select name="future_only" id="future_only" class="form-control">
                                            <option value="0" {{ $futureOnly == '0' ? 'selected' : '' }}>Alle Urlaube</option>
                                            <option value="1" {{ $futureOnly == '1' ? 'selected' : '' }}>Nur zukünftige Urlaube</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div>
                                            <button type="submit" class="btn btn-primary btn-block">
                                                <i class="fas fa-filter"></i> Filtern
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Ergebnisanzeige -->
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>{{ $holidays->total() }}</strong> genehmigte(r) Urlaub(e) gefunden
                        </div>

                        <!-- Urlaubstabelle -->
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Mitarbeiter</th>
                                        <th>Gruppe(n)</th>
                                        <th>Von</th>
                                        <th>Bis</th>
                                        <th>Tage</th>
                                        <th>Genehmigt am</th>
                                        <th>Genehmigt von</th>
                                        <th>Status</th>
                                        <th class="text-center">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($holidays as $holiday)
                                        @if($holiday->employe)
                                        <tr class="{{ $holiday->start_date->isPast() ? 'table-secondary' : '' }}">
                                            <td>
                                                <strong>{{ $holiday->employe->name }}</strong>
                                            </td>
                                            <td>
                                                @if($holiday->employe->groups_rel && $holiday->employe->groups_rel->count() > 0)
                                                    @foreach($holiday->employe->groups_rel as $group)
                                                        <span class="badge badge-info">{{ $group->name }}</span>
                                                    @endforeach
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>{{ $holiday->start_date->format('d.m.Y') }}</td>
                                            <td>{{ $holiday->end_date->format('d.m.Y') }}</td>
                                            <td>
                                                <span class="badge badge-primary">{{ $holiday->days }} Tag(e)</span>
                                            </td>
                                            <td>
                                                @if($holiday->approved_at)
                                                    {{ $holiday->approved_at->format('d.m.Y H:i') }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($holiday->approved_by && $holiday->approved_by_user)
                                                    {{ $holiday->approved_by_user->name }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($holiday->start_date->isPast())
                                                    <span class="badge badge-secondary">
                                                        <i class="fas fa-history"></i> Vergangen
                                                    </span>
                                                @else
                                                    <span class="badge badge-success">
                                                        <i class="fas fa-calendar-check"></i> Zukünftig
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-danger"
                                                        data-toggle="modal"
                                                        data-target="#deleteModal-{{ $holiday->id }}"
                                                        title="Urlaub löschen">
                                                    <i class="fas fa-trash"></i> Löschen
                                                </button>

                                                <!-- Lösch-Modal -->
                                                <div class="modal fade" id="deleteModal-{{ $holiday->id }}" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-danger text-white">
                                                                <h5 class="modal-title">
                                                                    <i class="fas fa-exclamation-triangle"></i> Urlaub wirklich löschen?
                                                                </h5>
                                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="alert alert-warning">
                                                                    <i class="fas fa-exclamation-triangle"></i>
                                                                    <strong>Achtung:</strong> Diese Aktion kann nicht rückgängig gemacht werden!
                                                                </div>
                                                                <p><strong>Mitarbeiter:</strong> {{ $holiday->employe->name }}</p>
                                                                <p><strong>Zeitraum:</strong> {{ $holiday->start_date->format('d.m.Y') }} - {{ $holiday->end_date->format('d.m.Y') }}</p>
                                                                <p><strong>Tage:</strong> {{ $holiday->days }}</p>
                                                                @if($holiday->start_date->isPast())
                                                                    <p class="text-danger">
                                                                        <i class="fas fa-info-circle"></i>
                                                                        Hinweis: Dieser Urlaub liegt in der Vergangenheit.
                                                                    </p>
                                                                @endif
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                                                    <i class="fas fa-times"></i> Abbrechen
                                                                </button>
                                                                <form action="{{ url('holidays/manage/delete/' . $holiday->id) }}" method="POST" class="d-inline">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-danger">
                                                                        <i class="fas fa-trash"></i> Ja, löschen
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        @endif
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                                <p>Keine genehmigten Urlaube mit den ausgewählten Filtern gefunden.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        @if($holidays->hasPages())
                            <div class="d-flex justify-content-center mt-4">
                                {{ $holidays->appends(request()->query())->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
<script>
    // Auto-Submit bei Änderung der Filter
    $('#user_id, #future_only').change(function() {
        $(this).closest('form').submit();
    });
</script>
@endpush
