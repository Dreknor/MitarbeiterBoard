@extends('personal.time_recording.layout')
@section('content')
    <div class="container">
        <!--vertical align on parent using my-auto-->
        <div class="row h-100">
            <div class="col-sm-12 my-auto">
                <div class="card bg-gradient-directional-teal">
                    <div class="card-header m-auto text-white border-bottom">
                        <h1>
                            digitale Zeiterfassung
                        </h1>
                    </div>
                    <div class="card-body">
                        <div class="w-25 mx-auto">
                            <div class="text-center text-light">
                                Läuft ab in:
                                <div class="autologouttimer">
                                    <div id="progressbar" class="progressbar color-red"></div>
                                    <span>1 Min.</span>
                                </div><br/>
                            </div>
                        </div>

                    </div>
                    <div class="card-body text-white" style="min-height: 25vH">
                        <h4 class="mx-auto text-center" id="hinweis">
                            Hallo {{$user->name}},<br>
                        </h4>
                        <p class="text-center">
                            @if(is_null($timesheet_day->end))
                                Arbeitszeitbeginn wurde für {{$timesheet_day->start->format('H:i')}} Uhr erfasst.
                            @else
                                Arbeitszeitende wurde für {{$timesheet_day->end->format('H:i')}} Uhr erfasst. <br>
                                Die erfasste Arbeitszeit betrug {{$timesheet_day->start->diff($timesheet_day->end)->format('%H:%I')}} Stunden.
                            @endif
                            <br>
                            <br>
                                <b>Aktueller Stand der Arbeitszeit:<br></b>
                            {{convertTime($timesheet->working_time_account)}} h
                        </p>
                        @if($dayBefore)
                            <p class="btn-outline-warning text-center">
                                Austragen der Arbeitszeit von gestern vergessen? <br>
                                Bitte noch nachtragen!
                            </p>
                        @endif
                    </div>
                    <div class="card-footer border-top">
                        <a href="{{route('time_recording.logout')}}" class="btn btn-danger btn-lg float-right">Abmelden</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@push('js')
    <script>

        $(document).ready(function() {
            /* Change time here to make the animation longer */
            $('#progressbar').animate({width: '0'}, 60000, 'linear', function () {
                window.location.href = "{{route('time_recording.logout')}}";
            });
        });
    </script>
@endpush
