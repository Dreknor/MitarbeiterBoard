<div class="card">
    <div class="card-header">
        @can('export absence')
            <div class="pull-right ml-2">
                <a href="{{url('absences/export')}}" class="card-link text-warning">
                    <i class="fa fa-file-export" title="Excel export"></i>
                    <div class="d-none d-md-block">
                        export
                    </div>
                </a>
            </div>
        @endcan
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
    <div class="card-body mt-2">
        <a href="#" class="btn btn-block btn-bg-gradient-x-blue-cyan" id="addAbsenceLink">
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
                <div class="col-md-6 col-sm-12">
                    <label>Von</label>
                    <input type="date" name="start" class="form-control" value="{{old('start', \Carbon\Carbon::now()->format('Y-m-d'))}}" required>
                </div>
                <div class="col-md-6 col-sm-12">
                    <label>Bis</label>
                    <input type="date" name="end" class="form-control" value="{{old('end', \Carbon\Carbon::now()->format('Y-m-d'))}}" required>
                </div>
            </div>
            <div class="form-row mt-1">
                <div class="@if(auth()->user()->can('create absences')) col-md-6 col-sm-12 @else col -12 @endif">
                    <label>
                        Anzeige Vertretungsplan
                    </label>
                    <select name="showVertretungsplan" class="custom-select">
                        <option value="1">anzeigen</option>
                        <option value="0">nicht anzeigen</option>
                    </select>
                </div>
                @if(auth()->user()->can('create absences'))
                    <div class="col-md-6 col-sm-12">
                        <label>
                            Krankenschein benötigt?
                        </label>
                        <select name="sick_note_required" class="custom-select">
                            <option value="0">nein</option>
                            <option value="1">ja</option>
                        </select>
                    </div>
                @endif
            </div>
            <div class="form-row mt-1">
                <div class=" col-12">
                    <label>Grund</label>
                    <input type="text" name="reason" class="form-control" value="{{old('reason', settings('absence_reason_default', 'absences'))}}" required>
                </div>
            </div>
            <div class="form-row mt-2">
                <div class="col-12">
                    <button type="submit" class="btn btn-success btn-block">speichern</button>
                </div>
            </div>
        </form>
    </div>
    <div class="card-body">
        @if(isset($absences) and $absences->count() > 0)
            <table class="table table-striped" id="absenceTable">
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
                                @if(auth()->user()->can('delete absences') or $absence->creator_id == auth()->id())
                                    <a href="{{url('absences/'.$absence->id.'/delete')}}">
                                        <i class="fas fa-trash text-danger"></i>
                                    </a>

                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            Keine Abwesenheiten vorhanden
        @endif
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

    <script src="//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready( function () {
            $('#absenceTable').DataTable({
                ordering: false
            });
        } );
    </script>

@endpush

@push('css')
    <link href="//cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap4.css" />

@endpush
