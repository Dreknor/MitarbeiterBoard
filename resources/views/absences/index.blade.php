<div class="card">
    <div class="card-header">
        <div class="pull-right ml-2">
            <a href="{{url('absences/abo/daily')}}" class="card-link text-success">
                @if(auth()->user()->absence_abo_daily != 1)
                    <i class="fa fa-bell" title="tägliche Zusammenfassung per E-Mail aktivieren"></i> <div class="d-none d-md-block">täglich</div>
                @else
                    <i class="fa fa-bell-slash" title="tägliche Zusammenfassung per E-Mail deaktivieren"></i>
                    <div class="d-none d-md-block">
                        täglich
                    </div>
                @endif
            </a>
        </div>
        <div class="pull-right ml-2">
            <a href="{{url('absences/abo/now')}}" class="card-link">
                @if(auth()->user()->absence_abo_now != 1)
                    <i class="fa fa-bell" title="sofortige Benachrichtigung per E-Mail aktivieren"></i>
                    <div class="d-none d-md-block">
                        sofort
                    </div>
                @else
                    <i class="fa fa-bell-slash" title="sofortige Benachrichtigung per E-Mail deaktivieren"></i>
                    <div class="d-none d-md-block">
                        sofort
                    </div>
                @endif
            </a>
        </div>
        <h6>
            Abwesenheiten
        </h6>

    </div>
    <div class="card-body">
        @if(isset($absences) and $absences->count() > 0)
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>
                            Name
                        </th>
                        <th>
                            Zeitraum
                        </th>
                        <th>
                            Grund
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($absences as $absence)
                        <tr>
                            <td>
                                {{$absence->user->name}}
                            </td>
                            <td>
                                @if($absence->showVertretungsplan)
                                    <i class="fas fa-columns text-info" title="Anzeige auf Vertretungsplan"></i>
                                @endif
                                {{$absence->start->format('d.m.Y')}} @if($absence->end->gt($absence->start))- {{$absence->end->format('d.m.Y')}}@endif
                            </td>
                            <td>
                                {{$absence->reason}}
                            </td>
                            <td>
                                @can('delete absences')
                                    <a href="{{url('absences/'.$absence->id.'/delete')}}">
                                        <i class="fas fa-trash text-danger"></i>
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="d-none d-lg-block">
                @can('view old absences')
                    <table class="table table-striped table-responsive">
                        <thead>
                        <tr>
                            <th colspan="4">
                                abgelaufene Abwesenheiten
                            </th>
                        </tr>
                        <tr>
                            <th>
                                Name
                            </th>
                            <th>
                                Zeitraum
                            </th>
                            <th>
                                Grund
                            </th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($oldAbsences as $absence)
                            <tr class="border border-info">
                                <td>
                                    {{$absence->user->name}}
                                </td>
                                <td>
                                    {{$absence->start->format('d.m.Y')}} @if($absence->end->gt($absence->start))- {{$absence->end->format('d.m.Y')}}@endif
                                </td>
                                <td>
                                    {{$absence->reason}}
                                </td>
                                <td>
                                    beendet
                                </td>
                            </tr>
                        @endforeach
                        <tr class="border-info">
                            <td colspan="4">
                                {{$oldAbsences->links()}}
                            </td>
                        </tr>
                        </tbody>
                    </table>
                @endcan
            </div>

        @else
            Keine Abwesenheiten vorhanden
        @endif
    </div>
    <div class="card-footer">
        <a href="#" class="card-link" id="addAbsenceLink">
            <i class="fa fa-plus-circle"></i> neue Abwesenheit
        </a>
        <form action="{{url('absences')}}" method="post" id="absenceForm" class="form-horizontal d-none">
            @csrf
            @if(auth()->user()->can('create absences'))
                <div class="form-row">
                    <label>
                        Mitarbeiter
                    </label>
                    <select name="users_id" class="custom-select">
                        @foreach(\App\Models\User::orderBy('name')->get() as $user)
                            <option value="{{$user->id}}">{{$user->name}}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" name="users_id" value="{{auth()->id()}}">
            @endif
            <div class="form-row">
                <div class="col-md-4 col-sm-12">
                    <label>Von</label>
                    <input type="date" name="start" class="form-control" value="{{old('start', \Carbon\Carbon::now())}}" required>
                </div>
                <div class="col-md-4 col-sm-12">
                    <label>Bis</label>
                    <input type="date" name="end" class="form-control" value="{{old('end', \Carbon\Carbon::now())}}" required>
                </div>
            </div>
            <div class="form-row mt-1">
                <div class=" col-md-4 col-sm-12">
                    <label>
                        Anzeige Vertretungsplan
                    </label>
                    <select name="showVertretungsplan" class="custom-select">
                            <option value="1">anzeigen</option>
                            <option value="0">nicht anzeigen</option>
                    </select>
                </div>
                <div class=" col-md-8 col-sm-12">
                    <label>Grund</label>
                    <input type="text" name="reason" class="form-control" value="{{old('reason', 'krank')}}" required>
                </div>
            </div>
            <div class="form-row mt-1">
                <button type="submit" class="btn btn-success btn-block">speichern</button>
            </div>
        </form>
    </div>
</div>

@push('js')
    <script type="text/javascript">
        $('#addAbsenceLink').on('click', function (ev){
            ev.preventDefault();
            $('#absenceForm').removeClass('d-none')
            ev.target.classList.add('d-none')
        })
    </script>
@endpush
