@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-10 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5>
                            Neue Liste anlegen
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{url('listen')}}" method="post" class="form-horizontal">
                            @csrf
                            <div class="form-row">
                                <div class="col-md-4 col-sm-12">
                                    <label for="listenname">
                                        Name der Liste
                                    </label>
                                    <input name="listenname" id="listenname" class="form-control" required>
                                </div>
                                <div class="col-md-4 col-sm-8">
                                    <label for="type">
                                        Listentyp
                                    </label>
                                    <select name="type" id="type" class="custom-select">
                                        <option value="termin" selected>
                                            Terminliste
                                        </option>
                                        <option value="eintrag" disabled>
                                            Eintrageliste
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-4 col-sm-4">
                                    <label for="duration">
                                        Dauer (in Minuten)
                                    </label>
                                    <input type="number" min="0" name="duration" id="duration" class="form-control" value="30">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class=" col-md-4 col-sm-6">
                                    <label for="ende">
                                        Ausblenden ab:
                                    </label>
                                    <input type="date" name="ende" value="{{\Carbon\Carbon::now()->addWeeks(6)->format('Y-m-d')}}" class="form-control" required>
                                </div>
                                <div class=" col-md-3 col-sm-6">
                                    <label for="visible_for_all">
                                       Einträge für alle sichtbar?
                                    </label>
                                    <select name="visible_for_all" class="custom-select" id="visible_for_all">
                                        <option value="0" selected>nur eigenen Eintrag anzeigen</option>
                                        <option value="1">alle dürfen alle Eintragungen sehen</option>
                                    </select>
                                </div>
                                <div class=" col-md-3 col-sm-6">
                                    <label for="multiple">
                                        mehrere Einträge buchbar?
                                    </label>
                                    <select name="multiple" class="custom-select" id="visible_for_all">
                                        <option value="0" selected>nein</option>
                                        <option value="1">ja</option>
                                    </select>
                                </div>
                                <div class=" col-md-3 col-sm-6">
                                    <label for="active">
                                        Liste aktivieren?
                                    </label>
                                    <select name="active" class="custom-select" id="active">
                                        <option value="1">ja</option>
                                        <option value="0" selected>noch nicht anzeigen</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-md-6 col-sm-12">
                                    <label for="comment">
                                        Beschreibung der Liste oder Hinweis
                                    </label>
                                    <textarea name="comment" id="comment" class="form-control"></textarea>
                                </div>
                                <div class="col-l-6 col-md-6 col-sm-12">
                                    <div class="card">
                                        <div class="card-body">
                                            @include('listen.formGroups')
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-md-12 col-sm-12">
                                    <button class="btn btn-success btn-block" type="submit">
                                        Liste erstellen
                                    </button>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

@endsection

@push('js')
    <!--
    <script src="{{asset('js/plugins/tinymce/jquery.tinymce.min.js')}}"></script>
    <script src="{{asset('js/plugins/tinymce/tinymce.min.js')}}"></script>
    <script src="{{asset('js/plugins/tinymce/langs/de.js')}}"></script>
    <script>tinymce.init({
            selector: 'textarea',
            lang:'de',
            height: 300,

        });</script>
        -->
@endpush
