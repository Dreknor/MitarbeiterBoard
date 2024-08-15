@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-4 col-md-5">
                <div class="card card-user">
                    <div class="image">
                        <img src="{{asset('img/bg-wall.jpg')}}" alt="...">
                    </div>
                    <div class="content">
                        <div class="author">
                            <img class="avatar border-white" src="{{$employe->photo()}}" alt="..." >
                            <h4 class="title">
                                {{ $employe->name }}
                            </h4>
                        </div>
                        <p class="description text-center">
                            <br>
                            <a href="#" class="btn btn-sm btn-primary" onclick="toogleForm()" id="photoButton">Foto ändern</a>
                        </p>

                    </div>
                    <div class="card-footer collapse" id="photoForm">
                        <form action="{{route('employes.self.photo')}}" method="post" enctype="multipart/form-data">
                            @csrf
                            <input type="file"  name="file" id="customFile" multiple accept=".jpg,.gif,.png" >
                            <button type="submit" class="btn btn-sm btn-primary">Foto ändern</button>
                        </form>
                    </div>
                </div>
                @if($employe->employments->count() > 0)
                    <div class="card">
                        <div class="card-header border-bottom">
                            <h5 class="card-title">
                                Arbeitsdaten
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group">
                                <li class="list-group-item">
                                    Angestellt seit: {{$employe->employments->first()?->start->format('d.m.Y')}}
                                </li>
                                <li class="list-group-item">
                                    Key-ID: {{$employe->employe_data?->time_recording_key}}
                                </li>
                                <li class="list-group-item">
                                    Pin: {{$employe->employe_data?->secret_key}}
                                </li>
                                <li class="list-group-item" id="Holidayclaim_list_item">
                                    Urlaubsanspruch: {{$employe->getHolidayClaim()}}
                                </li>
                                <li class="list-group-item">
                                    Stundenkonto: {{convertTime($employe->timesheet_latest?->working_time_account)}} h
                                </li>
                                <li class="list-group-item">
                                    montaliche Benachrichtigung Arbeitszeit: {{($employe->employe_data?->mail_timesheet == 1) ? 'ja' : 'nein' }}
                                </li>
                            </ul>
                        </div>
                    </div>

                @endif
                <div class="card">
                    <div class="card-header">
                        <h4 class="title">Gruppen</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled team-members">
                            @foreach($employe->groups() as $group)
                                <li>
                                    <a href="{{url($group->name.'/themes')}}" class="text-bold-600">
                                        {{$group->name}}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-8 col-md-7">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Profil bearbeiten</h4>
                    </div>
                    <div class="card-body">
                        <form action="{{url('employes/self')}}" method="post">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="text-danger">Vorname</label>
                                        <input type="text" name="vorname" class="form-control border-input" placeholder="Vorname" value="{{$employe?->employe_data?->vorname}}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Familienname</label>
                                        <input type="text" class="form-control border-input" name="familienname"  placeholder="Familienname" value="{{$employe?->employe_data?->familienname}}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Geburtsname</label>
                                        <input type="text" class="form-control border-input" name="geburtsname" placeholder="Geburtsname" value="{{$employe?->employe_data?->geburtsname}}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Staatsangehörigkeit</label>
                                        <input type="text" class="form-control border-input" name="staatsangehoerigkeit" placeholder="Staatsangehörigkeit" value="{{$employe?->employe_data?->staatsangehoerigkeit}}">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Geburtstag</label>
                                        <input type="date" name="geburtstag" class="form-control border-input" value="{{$employe?->employe_data?->geburtstag?->format('Y-m-d')}}">
                                    </div>
                                </div>
                                <div class="col-md-6">
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
                            </div>
                            <div class="row">
                                <div class="col-md-6 col-sm-12">
                                    <div class="form-group">
                                        <label >Geburtsort</label>
                                        <input type="text" name="geburtsort" class="form-control border-input" placeholder="Geburtsort" name="geburtsort"   value="{{old('geburtsort', $employe?->employe_data?->geburtsort)}}">
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
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="text-danger">Schwerbehindert?</label>
                                        <select name="schwerbehindert" class="custom-select" required>
                                            <option value="0" @if($employe?->employe_data?->schwerbehindert) selected @endif>nein</option>
                                            <option value="1" @if($employe?->employe_data?->schwerbehindert) selected @endif>ja</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                           <hr>
                            <h6>Einstellungen</h6>
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group ">
                                        <label class="text-danger">E-Mail-Benachrichtigung bei Abwesenheit oder Urlaub?</label>
                                        <select name="send_mail_if_absence" class="custom-select" required>
                                            <option value="0" @if($employe->send_mails_if_absence == false) selected @endif>nein</option>
                                            <option value="1" @if($employe->send_mails_if_absence == true) selected @endif>ja</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            @can('has timesheet')
                                <div class="row">
                                <div class="col-12">
                                    <h5>Google-Kalender nutzen?</h5>
                                    <p class="card-subtitle">
                                        Über diese Angaben kann du die Arbeitszeiten oder auch jeden einzelnen Dienstplan-Termin in deinen Google-Kalender eintragen lassen.<bR>
                                        Dazu wird die Kalender-ID des Google-Kalenders benötigt. Diese findest du in den Einstellungen deines Google-Kalenders.
                                    </p>
                                </div>
                                <div class="col-md-6 col-sm-6">
                                    <div class="form-group">
                                        <label class="text-danger">Arbeitszeiten eintragen?</label>
                                        <select name="caldav_working_time" class="custom-select" required>
                                            <option value="0" @if($employe?->employe_data?->caldav_working_time == 0) selected @endif>nein</option>
                                            <option value="1" @if($employe?->employe_data?->caldav_working_time == 1) selected @endif>ja</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-6">
                                    <div class="form-group">
                                        <label class="text-danger">Dienstplan-Termine eintragen?</label>
                                        <select name="caldav_events" class="custom-select" required>
                                            <option value="0" @if($employe?->employe_data?->caldav_events == 0) selected @endif>nein</option>
                                            <option value="1" @if($employe?->employe_data?->caldav_events == 1) selected @endif>ja</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Google Kalender ID</label>
                                        <input type="text" class="form-control border-input" placeholder="" name="google_calendar_link"  autocomplete="off" value="{{old('google_calendar_link', $employe?->employe_data?->google_calendar_link)}}">
                                    </div>
                                </div>
                            </div>
                            @endcan
                            <div class="text-center">
                                <button type="submit" class="btn btn-info btn-fill btn-wd">Speichern</button>
                            </div>
                            <div class="clearfix"></div>
                        </form>
                    </div>
                </div>
                @if($employe->employments->count() > 0)
                <div class="card">
                    <div class="card-header border-bottom">
                        <h5 class="card-title">
                            Anstellungen (derzeit: {{$employe->employments()->active()->get()->sum('percent')}}%
                            / {{$employe->employments()->active()->get()->sum('percent')*40/100}}h)
                        </h5>
                    </div>
                    <div class="card-body">
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
                            @foreach($employe->employments()->active()->get() as $employment)
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
                </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('css')

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/css/fileinput.min.css" media="all" rel="stylesheet" type="text/css" />

@endpush

@push('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/piexif.min.js" type="text/javascript"></script>



    <!-- piexif.min.js is needed for auto orienting image files OR when restoring exif data in resized images and when you
        wish to resize images before upload. This must be loaded before fileinput.min.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/piexif.min.js" type="text/javascript"></script>
    <!-- sortable.min.js is only needed if you wish to sort / rearrange files in initial preview.
        This must be loaded before fileinput.min.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/sortable.min.js" type="text/javascript"></script>
    <!-- purify.min.js is only needed if you wish to purify HTML content in your preview for
        HTML files. This must be loaded before fileinput.min.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/purify.min.js" type="text/javascript"></script>
    <!-- popper.min.js below is needed if you use bootstrap 4.x (for popover and tooltips). You can also use the bootstrap js
       3.3.x versions without popper.min.js. -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/fileinput.min.js"></script>
    <!-- following theme script is needed to use the Font Awesome 5.x theme (`fas`) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/themes/fas/theme.min.js"></script>

    <script>
        // initialize with defaults

        $("#customFile").fileinput({
            'showUpload':false,
            'previewFileType': 'any',
            maxFileSize: '{{config('app.maxFileSize', 100)}}',
            'theme': "fas",
        });

        function toogleForm() {
            $('#photoForm').toggle();
            $('#photoButton').toggle();
        }
    </script>


@endpush
