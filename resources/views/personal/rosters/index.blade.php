@extends('layouts.app')

@section('title')
    Dienstpläne
@endsection

@section('site-title')
    Mitarbeiterverwaltung
@endsection


@section('content')
    <div class="container-fluid">
        @foreach($departments as $department)
            <div class="card">
                <div class="card-header border-bottom">
                    <div class="row">
                        <div class="col">
                            <h5 class="card-title d-inline">
                                {{$department->name}}
                            </h5>
                            @can('create roster')
                                <div class=" pull-right ml-1 d-inline ">
                                    <a href="{{url('roster/create/'.$department->id)}}" class="">Dienstplan
                                        erstellen</a>
                                </div>
                                <div class=" pull-right d-inline ml-1">
                                    <div class="d-inline pull-right">
                                        <a href="#"
                                           @class(['link']) onclick="toggleAddCheck('{{$department->id}}_createCheck')">
                                            Erstelle Check
                                        </a>
                                    </div>
                                </div>
                            @endcan
                        </div>
                    </div>
                </div>
                <div
                    @class(['card-body', 'd-none', 'border-info']) id="{{$department->id}}_createCheck" @class(['form-horizontal'])>

                    <form action="{{route('roster.checks.store')}}" method="post">
                        @csrf
                        <input type="hidden" name="department_id" value="{{$department->id}}">
                        <div @class(['form-row'])>
                            <label for="check_name">
                                Bezeichnung
                            </label>
                            <input @class(['form-control']) name="check_name" required>
                        </div>
                        <div @class(['form-row'])>
                            <label for="field">
                                Type
                            </label>
                            <select @class(['custom-select']) name="field_name">
                                <option value="function">
                                    Aufgabe
                                </option>
                                <option value="start">
                                    Arbeitsbeginn
                                </option>
                                <option value="end">
                                    Arbeitsende
                                </option>
                                <option value="event">
                                    Terminname
                                </option>
                            </select>
                        </div>
                        <div @class(['form-row'])>
                            <div @class(['col-auto'])>
                                <label for="value">
                                    &nbsp;
                                </label>
                                <select @class(['custom-select']) name="operator">
                                    <option value="<">
                                        <
                                    </option>
                                    <option value="<=">
                                        <=
                                    </option>
                                    <option value="=" selected>
                                        =
                                    </option>
                                    <option value=">=">
                                        >=
                                    </option>
                                    <option value=">">
                                        >
                                    </option>
                                </select>
                            </div>
                            <div @class(['col'])>
                                <label for="value">
                                    Wert
                                </label>
                                <input @class(['form-control']) type="text" name="value" required>
                            </div>
                            <div @class(['col'])>
                                <label for="value">
                                    Anzahl
                                </label>
                                <input @class(['form-control']) type="number" min="1" value="1" name="needs" required>
                            </div>


                        </div>

                        <div @class(['form-row', 'mt-1'])>
                            <label @class(['label'])>
                                Montag
                                <input type="checkbox" @class(['custom-checkbox']) name="weekday[]" value="0" checked>
                            </label>
                            <label @class(['label', 'ml-1'])>
                                Dienstag
                                <input type="checkbox" @class(['custom-checkbox']) name="weekday[]" value="1" checked>
                            </label>
                            <label @class(['label', 'ml-1'])>
                                Mittwoch
                                <input type="checkbox" @class(['custom-checkbox']) name="weekday[]" value="2" checked>
                            </label>
                            <label @class(['label', 'ml-1'])>
                                Donnerstag
                                <input type="checkbox" @class(['custom-checkbox']) name="weekday[]" value="3" checked>
                            </label>
                            <label @class(['label', 'ml-1'])>
                                Freitag
                                <input type="checkbox" @class(['custom-checkbox']) name="weekday[]" value="4" checked>
                            </label>
                            <label @class(['label', 'ml-1'])>
                                Samstag
                                <input type="checkbox" @class(['custom-checkbox']) name="weekday[]" value="5">
                            </label>
                            <label @class(['label', 'ml-1'])>
                                Sonntag
                                <input type="checkbox" @class(['custom-checkbox']) name="weekday[]" value="6">
                            </label>
                        </div>
                        <div @class(["form-row", 'mt-1'])>
                            <input type="submit" @class(['btn', 'btn-bg-gradient-x-blue-green', 'btn-block'])>
                        </div>
                    </form>


                </div>
                <div class="card-body">
                    <table class="table table-hover rosterTable" id="">
                        <thead>
                        <tr>
                            <th></th>
                            <th>Gültig ab</th>
                            <th>Kommentar</th>
                            <th>Typ</th>
                            <th>Export</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($department->rosters->sortByDesc('start_date') as $roster)
                            <tr @if($roster->is_template) @class(['bg-info','bg-accent-2']) @endif>
                                <td>
                                    <a href="{{url('/roster/').'/'.$roster->id}}">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                                <td>
                                    {{$roster->start_date->format('Y-m-d')}}
                                </td>
                                <td>
                                    {{$roster->comment}}
                                </td>
                                <td>
                                    {{$roster->type}}
                                </td>
                                <td>
                                    <div @class(['container-fluid'])>
                                        <div @class(['row'])>
                                            @if(!$roster->isTemplate)
                                                <div @class(['col-auto'])>
                                                    <a href="{{route('roster.export.pdf',[$roster->id])}}">
                                                        <p>PDF</p>
                                                    </a>
                                                </div>
                                                @if(\Carbon\Carbon::now()->lt($roster->start_date))
                                                    <div @class(['col-auto'])>
                                                        <a href="{{route('roster.export.mail',[$roster->id])}}">E-Mail</a>
                                                    </div>
                                                @endif
                                                @if(!$roster->published)
                                                    <div @class(['col-auto'])>
                                                        <a href="{{route('roster.publish',[$roster->id])}}">veröffentlichen</a>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if(\Carbon\Carbon::now()->lt($roster->start_date))
                                        <div @class(['col-auto pull-right'])>
                                                        <form @class(['form-inline']) method="post"
                                                              action="{{route('roster.delete', $roster->id)}}">
                                                            @csrf
                                                            @method('delete')
                                                            <button type="submit" @class(['btn','btn-link', 'text-danger',  'p-0'])>
                                                                <i @class(['fa fa-trash'])></i>
                                                                <span @class(['d-md-inline', 'd-none'])>Löschen</span>
                                                            </button>
                                                        </form>
                                                    </div>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div @class(['card-footer'])>
                    <a href="#" @class(['card-link', 'check-link'])>Checks anzeigen</a>
                    @if($department->roster_checks->count() > 0)
                        <table @class(['table','table-striped', 'd-none'])>
                            <thead>
                            <tr>
                                <th>
                                    Art
                                </th>
                                <th>
                                    Feld
                                </th>
                                <th>
                                    Wert
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($department->roster_checks as $check)
                                <tr>
                                    <td>
                                        @switch($check->type)
                                            @case(\App\Models\personal\WorkingTime::class)
                                                Arbeitszeit
                                                @break
                                            @case(\App\Models\personal\RosterEvents::class)
                                                Termin
                                                @break
                                        @endswitch
                                    </td>
                                    <td>
                                        @switch($check->field_name)
                                            @case('function')
                                            Aufgabe
                                            @break
                                            @case('end')
                                            Arbeitsende
                                            @break
                                            @case('start')
                                            Arbeitsbeginn
                                            @break
                                        @endswitch

                                    </td>
                                    <td>
                                        {{$check->operator}}{{$check->value}} (mind. {{$check->needs}})
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

@endsection

@push('js')
    <script src="//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
    <script src="//cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function () {
            $('.rosterTable').DataTable();

            $('.check-link').on('click', function (ev) {
                var target = ev.target
                ev.preventDefault();
                $(target).next('table').toggleClass('d-none')
                $(target).text(function (i, text) {
                    return text === "Checks anzeigen" ? "Checks ausblenden" : "Checks anzeigen";
                })

            })
        });

        $('.link').on('click', function (ev) {
            ev.preventDefault();
        })

        function toggleAddCheck(id) {
            $('#' + id).toggleClass('d-none');
            $('#addEmploymentIcon').toggleClass('la-plus-square la-minus-circle text-danger')
        }
    </script>
@endpush

@section('css')
    <link href="//cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css" rel="stylesheet"/>
    <link href="//cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css" rel="stylesheet"/>
    <link href="//cdn.datatables.net/buttons/1.7.1/css/buttons.bootstrap4.min.css" rel="stylesheet"/>
    <link href="{{asset('css/style.css')}}" rel="stylesheet"/>

@endsection
