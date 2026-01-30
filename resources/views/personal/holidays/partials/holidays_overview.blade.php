<div class="card shadow-sm mb-4">
    <div class="card-header bg-secondary text-white">
        <h5>Meine Urlaubsübersicht</h5>
    </div>
    <div class="card-body p-2">
        <table class="table table-hover table-bordered">
            <thead>
            <tr>
                <th>Von</th>
                <th>Bis</th>
                <th>Tage</th>
                <th>Status</th>
                <th>Aktion</th>
            </tr>
            </thead>
            <tbody>
            @forelse(auth()->user()->holidays->filter(function($holiday) use ($month) {
                return $holiday->start_date->between($month->copy()->startOfYear(), $month->copy()->endOfYear());
            })->sortBy('start_date') as $holiday)

                <tr class="@foreach(auth()->user()->groups_rel as $group) {{$group->name}} @endforeach @if($holiday->start_date->isFuture()) table-warning @else bg-light-gray @endif">
                    <td>{{ $holiday->start_date->format('d.m.Y') }}</td>
                    <td>{{ $holiday->end_date->format('d.m.Y') }}</td>
                    <td>{{ $holiday->days }}</td>
                    <td>
                            <span class="badge {{ $holiday->approved ? 'badge-success' : 'badge-warning' }}">
                                {{ $holiday->approved ? 'Genehmigt' : 'Offen' }}
                            </span>
                    </td>
                    <td>
                        @if(!$holiday->approved or $holiday->start_date->isFuture())
                            <a href="{{ url('holidays/' . $holiday->id . '/delete') }}"
                               class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash"></i> Antrag löschen
                            </a>
                        @else
                            <span class="text-muted">Keine Aktion</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">Keine Urlaubsanträge gefunden.</td>
                </tr>
            @endforelse
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <strong>Summe:</strong>
                </td>
                <th>
                    @php
                        $sum = auth()->user()->holidays->filter(function($holiday) use ($month) {
                            return $holiday->start_date->between($month->copy()->startOfYear(), $month->copy()->endOfYear());
                        })->sum('days');
                    @endphp
                    {{ $sum }}
                </th>
                <td>
                    <strong>Rest:</strong> {{ auth()->user()->holiday_claim->last() ? auth()->user()->holiday_claim->last()->holiday_claim - $sum : settings('holiday_claim') - $sum }}
                </td>
            </tfoot>
        </table>
    </div>
</div>
@can('approve holidays')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5>
                    Urlaubstage
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-hover border table-responsive-sm">
                    <thead>
                    <tr>
                        <th class="border-right">Mitarbeiter</th>
                        <th class="border-right">Urlaub bisher/beantragt</th>
                        <th class="border-right">Rest</th>
                        <th class="border-right">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr class="@foreach($user->groups_rel as $group) {{$group->name}} @endforeach">
                                <td class="border-right w-25">
                                    {{$user->name}}
                                </td>
                                <td class="border-right">
                                    {{$user->holidays->filter(function($holiday) use ($month) {
                                        return $holiday->start_date->between($month->copy()->startOfYear(), \Carbon\Carbon::now());
                                    })->sum('days')}}
                                    / {{$user->holidays->filter(function($holiday) use ($month) {
                                        return $holiday->start_date->between(\Carbon\Carbon::now()->addDay(), $month->copy()->endOfYear());
                                    })->sum('days')}}
                                </td>
                                <td class="border-right">
                                    @if($user->holiday_claim->last())
                                        {{$user->holiday_claim->last()->holiday_claim - $user->holidays_date($month->copy()->startOfYear(), \Carbon\Carbon::now())->sum('days')}}
                                    @else
                                       {{settings('holiday_claim') - $user->holidays_date($month->copy()->startOfYear(), \Carbon\Carbon::now())->sum('days')}}
                                    @endif
                                </td>
                                <td class="border-right">
                                    <button class="btn btn-sm btn-outline-info" data-toggle="modal" data-target="#holidayDetailsModal-{{ $user->id }}">
                                        <i class="fas fa-eye"></i> Details
                                    </button>
                                </td>
                            </tr>
                        @endforeach

                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modals für detaillierte Urlaubsübersicht -->
@foreach($users as $user)
<div class="modal fade" id="holidayDetailsModal-{{ $user->id }}" tabindex="-1" aria-labelledby="holidayDetailsModalLabel-{{ $user->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="holidayDetailsModalLabel-{{ $user->id }}">
                    Detaillierte Urlaubsübersicht: {{ $user->name }}
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                @php
                    $userHolidaysThisYear = $user->holidays->filter(function($holiday) use ($month) {
                        return $holiday->start_date->between($month->copy()->startOfYear(), $month->copy()->endOfYear());
                    })->sortBy('start_date');

                    $takenHolidays = $userHolidaysThisYear->filter(function($holiday) {
                        return $holiday->start_date->isPast() && $holiday->approved;
                    });

                    $pendingHolidays = $userHolidaysThisYear->filter(function($holiday) {
                        return !$holiday->approved && !$holiday->rejected;
                    });

                    $approvedFutureHolidays = $userHolidaysThisYear->filter(function($holiday) {
                        return $holiday->start_date->isFuture() && $holiday->approved;
                    });

                    $rejectedHolidays = $userHolidaysThisYear->filter(function($holiday) {
                        return $holiday->rejected;
                    });

                    $totalClaim = $user->holiday_claim->last() ? $user->holiday_claim->last()->holiday_claim : settings('holiday_claim');
                    $takenDays = $takenHolidays->sum('days');
                    $pendingDays = $pendingHolidays->sum('days');
                    $approvedFutureDays = $approvedFutureHolidays->sum('days');
                    $previousYearRest = $user->getPreviousYearHolidayRest($month->year);
                    $remainingDays = $totalClaim + $previousYearRest - $takenDays - $approvedFutureDays;
                @endphp

                <!-- Übersicht -->
                <div class="row mb-3">
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body p-2">
                                <h6 class="text-muted small mb-1">Anspruch {{ $month->year }}</h6>
                                <h4 class="text-primary mb-0">{{ $totalClaim }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body p-2">
                                <h6 class="text-muted small mb-1">Rest {{ $month->year - 1 }}</h6>
                                <h4 class="text-{{ $previousYearRest < 0 ? 'danger' : 'warning' }} mb-0">{{ $previousYearRest }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body p-2">
                                <h6 class="text-muted small mb-1">Genommen</h6>
                                <h4 class="text-success mb-0">{{ $takenDays }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body p-2">
                                <h6 class="text-muted small mb-1">Genehmigt</h6>
                                <h4 class="text-info mb-0">{{ $approvedFutureDays }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body p-2">
                                <h6 class="text-muted small mb-1">Beantragt</h6>
                                <h4 class="text-warning mb-0">{{ $pendingDays }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center bg-light">
                            <div class="card-body p-2">
                                <h6 class="text-muted small mb-1"><strong>Verfügbar</strong></h6>
                                <h4 class="text-{{ $remainingDays < 0 ? 'danger' : 'dark' }} mb-0"><strong>{{ $remainingDays }}</strong></h4>
                            </div>
                        </div>
                    </div>
                </div>

                @if($previousYearRest != 0)
                <div class="alert alert-{{ $previousYearRest < 0 ? 'danger' : 'info' }} py-2">
                    <i class="fas fa-info-circle"></i>
                    <strong>Resturlaub {{ $month->year - 1 }}:</strong>
                    {{ $previousYearRest }} {{ $previousYearRest == 1 ? 'Tag' : 'Tage' }}
                    @if($user->timesheets()->where('year', $month->year - 1)->where('month', 12)->exists())
                        <span class="badge badge-secondary ml-2"><i class="fas fa-file-alt"></i> aus Timesheet</span>
                    @else
                        <span class="badge badge-secondary ml-2"><i class="fas fa-calculator"></i> berechnet</span>
                    @endif
                </div>
                @endif

                @if($pendingDays > 0)
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong>{{ $pendingDays }}</strong> Tage warten auf Genehmigung
                </div>
                @endif

                <!-- Genommene Urlaube -->
                @if($takenHolidays->count() > 0)
                <div class="mb-4">
                    <h6 class="text-success"><i class="fas fa-check-circle"></i> Genommene Urlaube ({{ $takenHolidays->count() }})</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Von</th>
                                <th>Bis</th>
                                <th>Tage</th>
                                <th>Genehmigt von</th>
                                <th>Genehmigt am</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($takenHolidays as $holiday)
                            <tr>
                                <td>{{ $holiday->start_date->format('d.m.Y') }}</td>
                                <td>{{ $holiday->end_date->format('d.m.Y') }}</td>
                                <td>{{ $holiday->days }}</td>
                                <td>{{ optional($holiday->approved_by)->name ?? '-' }}</td>
                                <td>{{ $holiday->approved_at ? $holiday->approved_at->format('d.m.Y') : '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                <!-- Genehmigte zukünftige Urlaube -->
                @if($approvedFutureHolidays->count() > 0)
                <div class="mb-4">
                    <h6 class="text-info"><i class="fas fa-calendar-check"></i> Genehmigte zukünftige Urlaube ({{ $approvedFutureHolidays->count() }})</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Von</th>
                                <th>Bis</th>
                                <th>Tage</th>
                                <th>Genehmigt von</th>
                                <th>Genehmigt am</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($approvedFutureHolidays as $holiday)
                            <tr>
                                <td>{{ $holiday->start_date->format('d.m.Y') }}</td>
                                <td>{{ $holiday->end_date->format('d.m.Y') }}</td>
                                <td>{{ $holiday->days }}</td>
                                <td>{{ optional($holiday->approved_by)->name ?? '-' }}</td>
                                <td>{{ $holiday->approved_at ? $holiday->approved_at->format('d.m.Y') : '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                <!-- Beantragte/Offene Urlaube -->
                @if($pendingHolidays->count() > 0)
                <div class="mb-4">
                    <h6 class="text-warning"><i class="fas fa-clock"></i> Beantragte Urlaube ({{ $pendingHolidays->count() }})</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Von</th>
                                <th>Bis</th>
                                <th>Tage</th>
                                <th>Beantragt am</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingHolidays as $holiday)
                            <tr>
                                <td>{{ $holiday->start_date->format('d.m.Y') }}</td>
                                <td>{{ $holiday->end_date->format('d.m.Y') }}</td>
                                <td>{{ $holiday->days }}</td>
                                <td>{{ $holiday->created_at->format('d.m.Y H:i') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                <!-- Abgelehnte Urlaube -->
                @if($rejectedHolidays->count() > 0)
                <div class="mb-4">
                    <h6 class="text-danger"><i class="fas fa-times-circle"></i> Abgelehnte Urlaube ({{ $rejectedHolidays->count() }})</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Von</th>
                                <th>Bis</th>
                                <th>Tage</th>
                                <th>Abgelehnt am</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rejectedHolidays as $holiday)
                            <tr>
                                <td>{{ $holiday->start_date->format('d.m.Y') }}</td>
                                <td>{{ $holiday->end_date->format('d.m.Y') }}</td>
                                <td>{{ $holiday->days }}</td>
                                <td>{{ $holiday->updated_at->format('d.m.Y H:i') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                @if($userHolidaysThisYear->count() == 0)
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Keine Urlaubseinträge für {{ $month->year }} gefunden.
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>
@endforeach
@endcan
