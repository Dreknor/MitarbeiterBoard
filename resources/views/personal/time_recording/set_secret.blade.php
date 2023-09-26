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
                        <p id="pin_show"  class="mx-auto text-center text-light">
                            Pin:
                        </p>
                        <p class="mx-auto text-center">
                            Bitte vergib eine geheime Pin zur Absicherung deines Accounts. Sie muss mindestens 6 Zeichen lang sein.
                        </p>
                        <div class="w-50  mx-auto">
                            <div class="row">
                                <div class="col-4 mx-auto">
                                    <button class="btn btn-lg btn-info" onclick="addNumber(1)">1</button>
                                </div>
                                <div class="col-4 mx-auto">
                                    <button class="btn btn-lg btn-info" onclick="addNumber(2)">2</button>
                                </div>
                                <div class="col-4 mx-auto">
                                    <button class="btn btn-lg btn-info" onclick="addNumber(3)">3</button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-4 mx-auto">
                                    <button class="btn btn-lg btn-info" onclick="addNumber(4)">4</button>
                                </div>
                                <div class="col-4 mx-auto">
                                    <button class="btn btn-lg btn-info" onclick="addNumber(5)">5</button>
                                </div>
                                <div class="col-4 mx-auto">
                                    <button class="btn btn-lg btn-info" onclick="addNumber(6)">6</button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-4 mx-auto">
                                    <button class="btn btn-lg btn-info" onclick="addNumber(7)">7</button>
                                </div>
                                <div class="col-4 mx-auto">
                                    <button class="btn btn-lg btn-info" onclick="addNumber(8)">8</button>
                                </div>
                                <div class="col-4 mx-auto">
                                    <button class="btn btn-lg btn-info" onclick="addNumber(9)">9</button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-4 mx-auto">
                                    <button class="btn btn-block btn-success" onclick="submitForm()">absenden</button>
                                </div>
                                <div class="col-4 mx-auto">
                                    <button class="btn btn-lg btn-info" onclick="addNumber(0)">0</button>
                                </div>
                                <div class="col-4 mx-auto">
                                    <button class="btn btn-block btn-danger" onclick="clearInput()">Eingabe löschen</button>
                                </div>
                            </div>
                        </div>

                        @if ($errors->any())
                            <p>
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            </p>
                        @endif

                        <form action="{{route('time_recording.storeSecret')}}" method="post" class="form-horizontal"  autocomplete="off" id="pinForm">
                            @csrf
                            <input type="hidden" id="secret_key" name="secret_key"   aria-autocomplete="none">
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>

@endsection
@push('js')
    <script>
        function addNumber(number) {
            let secret_key = document.getElementById('secret_key');
            secret_key.value += number;
            let pin_show = document.getElementById('pin_show');
            pin_show.innerHTML += '*';
        }

        function clearInput(){
            let secret_key = document.getElementById('secret_key');
            secret_key.value = '';
            let pin_show = document.getElementById('pin_show');
            pin_show.innerHTML = 'Pin: ';

        }

        function submitForm() {
            $('#pinForm').submit();

        }

        $(document).ready(function() {
            /* Change time here to make the animation longer */
            $('#progressbar').animate({width: '0'}, 60000, 'linear', function () {
                window.location.href = "{{route('time_recording.logout')}}";
            });
        });
    </script>
@endpush
