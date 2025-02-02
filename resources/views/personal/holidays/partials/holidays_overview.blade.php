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
                        @if(!$holiday->approved or (auth()->user()->can('approve holidays') and $holiday->start_date->isFuture()))
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
                            </tr>
                        @endforeach

                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@endcan
