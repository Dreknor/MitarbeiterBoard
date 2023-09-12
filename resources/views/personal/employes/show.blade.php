@extends('layouts.app')

@section('site-title')
    {{$employe->vorname}} {{$employe->familienname}}
@endsection

@section('title')
    Personalverwaltung
@endsection


@section('content')
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col-auto">
                        @if($employe->getFirstMedia('imgage'))

                        @else
                            <img src="data:image/jpeg;base64,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" class="rounded-circle img-border height-100" alt="Card image">
                        @endif
                    </div>
                    <div class="col">
                        <h5>
                            Allgemeines ({{$employe->vorname}} {{$employe->familienname}})
                            @can('edit employe')
                                <div class="d-inline pull-right">
                                    <a href="#" onclick="toggleEditEmployeForm()">
                                        <i class="fa fa-edit" id="EditEmployeIcon"></i>
                                    </a>
                                </div>
                            @endcan
                        </h5>
                        <div class="row">
                            <div class="col-lg-4 col-sm-auto">
                                <div class="font-weight-bold d-inline">
                                    Sozialversicherungsnummer:
                                </div>
                                    {{$employe?->employe_data?->sozialversicherungsnummer}}
                            </div>
                            <div class="col-lg-4 col-sm-auto">
                                <div class="font-weight-bold d-inline">
                                    Geschlecht:
                                </div>
                                    {{$employe?->employe_data?->geschlecht}}
                            </div>
                            <div class="col-lg-4 col-sm-auto">
                                <div class="font-weight-bold d-inline">
                                    Geburtstag:
                                </div>
                                    {{optional($employe->geburtstag)->format('d.m.Y')}} @if(!is_null($employe->geburtstag)) ({{$employe->geburtstag->diffInYears(Carbon\Carbon::now())}} Jahre) @endif
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-4 col-sm-auto">
                                <div class="font-weight-bold d-inline">
                                    Geburtsort:
                                </div>
                                    {{$employe?->employe_data?->geburtsort}}
                            </div>
                            <div class="col-lg-4 col-sm-auto">
                                <div class="font-weight-bold d-inline">
                                    Geburtsname:
                                </div>
                                {{$employe?->employe_data?->geburtsname}}
                            </div>
                            <div class="col-lg-4 col-sm-auto">
                                <div class="font-weight-bold d-inline">
                                    Staatsangehörigkeit:
                                </div>
                                {{$employe?->employe_data?->staatsangehoerigkeit}}
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-4 col-sm-auto">
                                <div class="font-weight-bold d-inline">
                                    Schwerbehindert:
                                </div>
                                @if($employe?->employe_data?->schwerbehindert) ja @else nein @endif
                            </div>
                            <div class="col-lg-8 col-sm-12">
                                <div class="font-weight-bold d-inline">
                                    Kalender:
                                </div>
                                @if($employe?->employe_data?->caldav_uuid)
                                    <a href="{{url('ical/'.$employe->id.'/'.$employe?->employe_data?->caldav_uuid)}}" class="card-link">
                                        {{url('ical/'.$employe->id.'/'.$employe?->employe_data?->caldav_uuid)}}
                                    </a>
                                @else
                                    keine Freigabe
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body d-none" id="editEmpolyeForm">
                <form action="{{route('employes.update', [$employe->id])}}" method="post">
                @csrf
                @method('put')
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label class="text-danger">Familienname</label>
                                <input type="text" class="form-control border-input" name="familienname" required autocomplete="off" value="{{old('familienname', $employe?->employe_data?->familienname)}}">
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label class="text-danger">Vorname</label>
                                <input type="text" class="form-control border-input" placeholder="Vorname" name="vorname" required autocomplete="off" value="{{old('vorname', $employe?->employe_data?->vorname)}}">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label class="text-danger">Geburtsdatum</label>
                                <input type="date" class="form-control border-input" name="geburtstag" required autocomplete="off" value="{{old('geburtstag', $employe?->employe_data?->geburtstag?->format('Y-m-d'))}}">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label class="text-danger">Geschlecht</label>
                                <select name="geschlecht" class="custom-select" required>
                                    <option disabled></option>
                                    <option value="männlich" @if($employe?->employe_data?->geschlecht == "männlich") selected @endif>männlich</option>
                                    <option value="weiblich" @if($employe?->employe_data?->geschlecht == "weiblich") selected @endif>weiblich</option>
                                    <option value="anderes" @if($employe?->employe_data?->geschlecht == "anderes") selected @endif>anderes</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label>Geburtsname</label>
                                <input type="text" class="form-control border-input" placeholder="Geburtsname" name="geburtsname" autocomplete="off" value="{{old('geburtsname', $employe?->employe_data?->geburtsname)}}">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label >Geburtsort</label>
                                <input type="text" class="form-control border-input" placeholder="Geburtsort" name="geburtsort"   value="{{old('geburtsort', $employe?->employe_data?->geburtsort)}}">
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label>Sozialversicherungsnummer</label>
                                <input type="text" class="form-control border-input" placeholder="Sozialversicherungsnummer" name="sozialversicherungsnummer"  autocomplete="off" value="{{old('sozialversicherungsnummer', $employe?->employe_data?->sozialversicherungsnummer)}}">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label class="text-danger">Schwerbehindert?</label>
                                <select name="schwerbehindert" class="custom-select" required>
                                    <option value="0" @if($employe?->employe_data?->schwerbehindert) selected @endif>nein</option>
                                    <option value="1" @if($employe?->employe_data?->schwerbehindert) selected @endif>ja</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label class="text-danger">Staatsangehörigkeit</label>
                                <input type="text" class="form-control border-input" value="deutsch" name="staatsangehoerigkeit" required autocomplete="off" value="{{old('staatsangehoerigkeit', $employe?->employe_data?->staatsangehoerigkeit)}}">
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label class="text-danger">ICal - Arbeitszeiten?</label>
                                <select name="caldav_working_time" class="custom-select" required>
                                    <option value="0" @if($employe?->employe_data?->caldav_working_time == 0) selected @endif>nein</option>
                                    <option value="1" @if($employe?->employe_data?->caldav_working_time == 1) selected @endif>ja</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label class="text-danger">ICal - Termine?</label>
                                <select name="caldav_events" class="custom-select" required>
                                    <option value="0" @if($employe?->employe_data?->caldav_events == 0) selected @endif>nein</option>
                                    <option value="1" @if($employe?->employe_data?->caldav_events == 1) selected @endif>ja</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-12">
                            <div class="form-group">
                                <label>Google Kalender ID</label>
                                <input type="text" class="form-control border-input" placeholder="" name="google_calendar_link"  autocomplete="off" value="{{old('google_calendar_link', $employe?->employe_data?->google_calendar_link)}}">
                            </div>
                        </div>
                    </div>
                <button type="submit" class="btn btn-bg-gradient-x-blue-green btn-block">Speichern</button>
            </form>
        </div>
        </div>

        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                    <div class="card-header border-bottom">
                        <h5 class="card-title">
                            Arbeitsdaten
                            @can('edit employe')
                                <div class="d-inline pull-right">
                                    <a href="#" onclick="toogleEditHolidayClaimForm()">
                                        <i class="fa fa-edit" id="EditEmployeDataIcon"></i>
                                    </a>
                                </div>
                            @endcan
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="">
                            <li class="">
                                Angestellt seit: ???
                            </li>
                            <li class="">
                                Key-ID: {{$employe->employe_data->time_recording_key}}
                            </li>
                            <li class="" id="Holidayclaim_list_item">
                                Urlaubsanspruch: {{$employe->getHolidayClaim()}}
                            </li>
                            <li class="">
                                 Stundenkonto: {{convertTime($employe->timesheet_latest?->working_time_account)}} h
                                (<a href="{{url('timesheets/update/employe/'.$employe->id)}}" class="card-link">aktualiseren</a>)
                            </li>
                        </ul>
                    </div>
                        <div class="card-body d-none" id="editEmpolyeDataForm">
                            <div class="container-fluid">
                                <form method="post" action="{{route('employes.data.update', [$employe->id])}}" class="form-horizontal" id="editHolidayClaimForm">
                                    @csrf
                                    @method('put')
                                    <div class="row">
                                        <label class="label-control">
                                            Arbeitszeit - Key-Nummer
                                        </label>
                                        <input name="time_recording_key" type="number" min="1" value="{{$employe->employe_data->time_recording_key}}" class="form-control">
                                    </div>
                                    <div class="row">
                                        <label class="label-control">
                                            Arbeitszeit - Pin
                                        </label>
                                        <input name="secret_key" type="password" value="{{$employe->employe_data->secret_key}}" class="form-control">
                                    </div>
                                    <div class="row">
                                        <label class="label-control">
                                            Urlaubsanspruch
                                        </label>
                                        <input name="holidayClaim" type="number" min="1" value="{{$employe->getHolidayClaim()}}" class="form-control">
                                    </div>
                                    <div class="row">
                                        <label class="label-control">
                                            ab ...
                                        </label>
                                        <input name="date_start" type="date" value="{{\Carbon\Carbon::now()->format('Y-m-d')}}" class="form-control">
                                    </div>


                                    <button type="submit" form="editHolidayClaimForm" class="btn btn-sm btn-success">speichern</button>
                                </form>
                            </div>
                        </div>
                        @if(!is_null($employe->salary))
                            <div class="card-footer d-none" id="editSalaryForm">
                            <form class="form-horizontal" method="post" action="{{url('employe/'.$employe->id.'/editSalary')}}">
                                @csrf
                                @method('put')
                                <div class="row">
                                    <label for="table">
                                        Gehaltstabelle
                                    </label>
                                    <select class="custom-select" name="salary_table_id" id="salary_table_select">
                                        @foreach($salaryTables as $table)
                                            <option value="{{$table->id}}" @if($employe->salaryTable == $table->name) selected @endif>{{$table->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row">
                                    <label for="table">
                                        Gruppe
                                    </label>
                                    <select class="custom-select" name="group" required>
                                        @foreach($salaryTables as $table)
                                            @foreach($table->groups as $group)
                                                <option class="groupoption {{$table->id}}  @if($employe->salaryTable != $table->name) d-none @endif" value="{{$group}}"  @if($employe->salary->group == $group) selected @endif>
                                                   {{$group}}
                                                </option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row">
                                    <label for="table">
                                        Stufe
                                    </label>
                                    <select class="custom-select" name="level"  required>
                                        @foreach($salaryTables as $table)
                                            @foreach($table->levels as $level)
                                                <option class="leveloption {{$table->id}}  @if($employe->salaryTable != $table->name) d-none @endif" value="{{$level}}" @if($employe->salary->level == $level) selected @endif>
                                                   {{$level}}
                                                </option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row">
                                    <label for="start">ab..</label>
                                    <input type="date" name="start" class="form-control" min="{{\Carbon\Carbon::now()->format('Y-m-d')}}" value="{{\Carbon\Carbon::now()->format('Y-m-d')}}">
                                </div>
                                <div class="row mt-1">
                                    <button class="btn btn-bg-gradient-x-blue-green btn-block" type="submit">speichern</button>
                                </div>
                            </form>
                        </div>
                        @endif

                </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header border-bottom">
                            <h5 class="card-title">
                                Anstellungen (derzeit: {{$employe->employments()->active()->get()->sum('percent')}}%
                                / {{$employe->employments()->active()->get()->sum('percent')*40/100}}h)
                                @can('edit employe')
                                    <div class="d-inline pull-right">
                                        <a href="#" onclick="toggleAddEmpolyment()">
                                            <i class="fa fa-plus-square" id="addEmploymentIcon"></i>
                                        </a>
                                    </div>
                                @endcan
                            </h5>
                        </div>
                        <div class="card-body d-none" id="addEmploymentForm">
                            <form action="{{url('employments/'.$employe->id.'/add')}}" method="post">
                                @csrf
                                <div class="row">
                                    <div class="col">
                                        <label for="department" class="text-danger">Bereich</label>
                                        <select name="department_id" class="custom-select">
                                            @foreach($departments as $department)
                                                <option value="{{$department->id}}" style="color: {{$department->color}};">
                                                    {{$department->name}}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                </div>
                                <div class="row">
                                    <div class="col">
                                        <label for="hour_type_id" class="text-danger">Stundentyp</label>
                                        <select name="hour_type_id" class="custom-select">
                                            @foreach($hour_types as $hour_type)
                                                <option value="{{$hour_type->id}}">
                                                    {{$hour_type->name}}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <label class="text-danger w-100">
                                            Stunden
                                            <input type="number" step="0.01" name="hours" class="form-control">
                                        </label>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class=" col-md-6 col-sm-12">
                                        <label class="text-danger">
                                            Ab ...
                                            <input type="date" name="start" class="form-control">
                                        </label>
                                    </div>
                                    <div class=" col-md-6 col-sm-12">
                                        <label>
                                            befristet bis ...
                                            <input type="date" name="end" class="form-control">
                                        </label>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <label class="w-100">
                                            Bemerkung
                                            <input type="text" name="comment" class="form-control">
                                        </label>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <label for="salary_type" class="text-danger">Bezahlung</label>
                                        <select name="salary_type" id="salary_type" class="custom-select">
                                            <option value="salary_table">Gehaltsstufe</option>
                                            <option value="invoice">Rechnung</option>
                                            <option value="one-time">Einmalig</option>
                                            <option value="salary">Gehalt (monatlich)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row d-none" id="salaryInputRow">
                                    <div class="col">
                                        <label class="w-100">
                                            Gehalt
                                            <input type="number" step="0.01" name="salary" class="form-control">
                                        </label>
                                    </div>
                                </div>
                                @if($employe->employments->where('end', '')->count() > 0)
                                    <div class="row">
                                        <div class="col">
                                            <label for="replaced_employment_id">Ersetzt Anstellung vom ?</label>
                                            <select name="replaced_employment_id" id="replaced_employment_id"
                                                    class="custom-select">
                                                <option value="">keine</option>
                                                @foreach($employe->employments->where('end', '') as $employment)
                                                    <option
                                                        value="{{$employment->id}}">{{$employment->start->format('d.m.Y')}} {{$employment->department->name}} {{$employment->comment}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @endif
                                <div class="row mt-1">
                                    <button type="submit" class="btn btn-block btn-bg-gradient-x-blue-green">
                                        speichern
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#current">Current</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#past">Past</a>
                                </li>
                            </ul>
                            <div class="tab-content">
                                <div id="current" class="tab-pane active">
                                    <table class="table table-bordered">
                                        <thead>
                                        <tr>
                                            <th>Bereich</th>
                                            <th>Stunden</th>
                                            <th>Start</th>
                                            <th>Ende</th>
                                            <th>Kommentar</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($employments as $employment)
                                            <tr>
                                                <td >
                                                    {{$employment->department->name}}
                                                </td>
                                                <td>
                                                    {{$employment->hours}} Stunden ({{$employment->percent}}%)
                                                </td>
                                                <td>
                                                    vom {{$employment->start->format('d.m.Y')}}
                                                </td>
                                                <td>
                                                    @if(!is_null($employment->end))
                                                        bis {{$employment->end->format('d.m.Y')}}
                                                    @endif
                                                </td>
                                                <td>
                                                    {{$employment->comment}}
                                                </td>
                                            </tr>
                                            </li>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div id="past" class="tab-pane">
                                    <table class="table table-bordered">
                                        <thead>
                                        <tr>
                                            <th>Bereich</th>
                                            <th>Stunden</th>
                                            <th>Start</th>
                                            <th>Ende</th>
                                            <th>Kommentar</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($employments_old as $employment)
                                            <tr>
                                                <td style="background-color: {{$employment->department->color}} ">
                                                    {{$employment->department->name}}
                                                </td>
                                                <td>
                                                    {{$employment->hours}} Stunden ({{$employment->percent}}%)
                                                </td>
                                                <td>
                                                    vom {{$employment->start->format('d.m.Y')}}
                                                </td>
                                                <td>
                                                    @if(!is_null($employment->end))
                                                        bis {{$employment->end->format('d.m.Y')}} @endif
                                                </td>
                                                <td>
                                                    @if(!is_null($employment->comment))
                                                        {{$employment->comment}}
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
                </div>

            </div>
            <div class="row">
                <div class="col-md-6 col-sm-12">
                        <div class="card">
                            <div class="card-header border-bottom">
                                <h5 class="card-title">
                                    Dokumente
                                </h5>
                            </div>
                            <div class="card-body">

                            </div>
                        </div>
                    </div>
            </div>
        </div>
@endsection


@push('js')
    @can('edit employe')
        <script>
            $('#salary_type').on('change', function() {
                if (this.value != 'salary_table'){
                    $('#salaryInputRow').removeClass('d-none')
                } else {
                    $('#salaryInputRow').addClass('d-none')
                }
            });
            $('#salary_table_select').on('change', function() {
                $('.groupoption').addClass('d-none')
                $('.leveloption').addClass('d-none')
                $('.'+this.value).toggleClass('d-none')
            });

            function toggleAddEmpolyment(){
                $('#addEmploymentForm').toggleClass('d-none');
                $('#addEmploymentIcon').toggleClass('la-plus-square la-minus-circle text-danger')
            }
            function toggleAddSalary(){
                $('#addSalaryForm').toggleClass('d-none');
                $('#addSalaryIcon').toggleClass('la-plus-square la-minus-circle text-danger')
            }
            function toggleEditSalary(){
                $('#editSalaryForm').toggleClass('d-none');
                $('#editSalaryIcon').toggleClass('la-edit la-minus-circle text-danger')
            }

            function toggleAnschrift(){
                $('#anschrift').toggleClass('d-none');
                $('#anschriftForm').toggleClass('d-none');
                $('#addressIcon').toggleClass('fa fa-edit fa fa-minus-circle text-danger')
            }
            function toggleContact(){
                $('#contactForm').toggleClass('d-none');
                $('#ContactIcon').toggleClass('fa fa-edit fa fa-minus-circle text-danger')
                $('.deleteContact').toggleClass('d-none')
            }

            function toggleEditEmployeForm(){
                $('#editEmpolyeForm').toggleClass('d-none');
                $('#EditEmployeIcon').toggleClass('fa fa-edit fa fa-minus-circle text-danger')
            }

            function toogleEditHolidayClaimForm(){
                $('#editEmpolyeDataForm').toggleClass('d-none');
                $('#EditEmployeDataIcon').toggleClass('fa fa-edit fa fa-minus-circle text-danger')
            }

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': "{{ csrf_token() }}"
                }
            });

            $(".deleteBtn").click(function(e){

                e.preventDefault();
                var id =  $(this).data('id');
                var url = "{{url('contacts/')}}";
                url = url + '/' + id;

                $.ajax({
                    type:'DELETE',
                    url:url,
                    success:function(data){
                        $('#contact_'+id).addClass('d-none');
                    }
                });

            });
        </script>
    @endcan
@endpush
