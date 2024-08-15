@extends('layouts.app')
@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    Gruppe bearbeiten
                </h5>
            </div>
            <div class="card-body">
                <form action="{{url('groups/'.$gruppe->id)}}" method="post" class="form-horizontal">
                    @csrf
                    @method('patch')
                    <div class="form-row">
                        <label for="name">Name der Gruppe</label>
                        <input type="text" class="form-control" name="name" id="name" required autofocus value="{{old('name', $gruppe->name)}}">
                    </div>
                    <div class="form-row mt-1">
                        <div class="col-md-6">
                            <label for="meeting_day">Besprechungen an Wochentag</label>
                            <select name="meeting_weekday" class="custom-select" >
                                <option  @if(!$gruppe->meeting_weekday) selected @endif></option>
                                <option value="1" @if($gruppe->meeting_weekday == 1) selected @endif>Montag</option>
                                <option value="2" @if($gruppe->meeting_weekday == 2) selected @endif>Dienstag</option>
                                <option value="3" @if($gruppe->meeting_weekday == 3) selected @endif>Mittwoch</option>
                                <option value="4" @if($gruppe->meeting_weekday == 4) selected @endif>Donnerstag</option>
                                <option value="5" @if($gruppe->meeting_weekday == 5) selected @endif>Freitag</option>
                                <option value="6" @if($gruppe->meeting_weekday == 6) selected @endif>Samstag</option>
                                <option value="7" @if($gruppe->meeting_weekday == 7) selected @endif>Sonntag</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="InvationDays">Tage, die ein Thema vorher angelegt sein muss</label>
                            <input type="number" class="form-control" name="InvationDays" id="InvationDays" value="{{old('InventionDays', $gruppe->InvationDays)}}"  min="1" required>
                        </div>
                    </div>
                    <div class="form-row mt-1">
                        <div class="col-6">
                            <label for="enddate">aktiv bis</label>
                            <input type="date" class="form-control" name="enddate" id="enddate" value="{{old('enddate', optional($gruppe->enddate)->format('Y-m-d'))}}" @if(!auth()->user()->can('edit groups')) max="{{\Carbon\Carbon::now()->addYear()->format('Y-m-d')}}" required @endif>
                        </div>
                        <div class="col-md-6">
                            <label for="homegroup">überführen in</label>
                            <select name="homegroup" class="custom-select">
                                <option disabled></option>
                                @foreach($groups->where('enddate', '') as $newgroup)
                                    <option value="{{$newgroup->id}}" @if($gruppe->homegroup == $newgroup->id) selected @endif>{{$newgroup->name}}</option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                    <div class="form-row mt-1">
                        <label for="name">Geschützt?</label>
                        <select name="protected" class="custom-select">
                            <option value="1" @if ($gruppe->protected) selected @endif>Gruppe nur für Mitglieder</option>
                            <option value="0" @if (!$gruppe->protected) selected @endif>für alle sichtbar</option>
                        </select>
                    </div>

                    <div class="form-row mt-1">
                        <label for="viewType">Ansicht?</label>
                        <select name="viewType" id="viewType" class="custom-select">
                            <option value="date" @if($gruppe->viewType == 'date') selected @endif>nach Datum</option>
                            <option value="priority" @if(!$gruppe->viewType == 'priority') selected @endif>Priorität</option>
                            <option value="type" @if(!$gruppe->viewType == 'type') selected @endif>Themen-Typ</option>
                        </select>
                    </div>
                    <div class="form-row mt-1">
                        <label for="hasWochenplan">Wochenplan?</label>
                        <select name="hasWochenplan" id="hasWochenplan" class="custom-select">
                            <option value="1" @if($gruppe->hasWochenplan) selected @endif>braucht Wochenplan</option>
                            <option value="0" @if(!$gruppe->hasWochenplan) selected @endif>kein Wochenplan</option>
                        </select>
                    </div>
                    <div class="form-row mt-1">
                        <label for="hasAllocations">ganze Themen einem Benutzer zuweisen?</label>
                        <select name="hasAllocations" id="hasAllocations" class="custom-select">
                            <option value="0" @if(!$gruppe->hasAllocations) selected @endif>nein</option>
                            <option value="1" @if($gruppe->hasAllocations) selected @endif>ja</option>
                        </select>
                    </div>
                    <div class="form-row pt-2">
                        <div class="col-sm-12 col-md-12 col-lg-12">
                            <label for="information_template">Vorlage Informationen</label>
                            <textarea class="form-control" id="information_template" name="information_template">
                            {{old('information_template')}}
                        </textarea>
                        </div>
                    </div>
                    <div class="form-row">
                        <button type="submit" class="btn btn-success btn-block">
                            speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection


@push('js')
    <script src="{{asset('js/plugins/tinymce/jquery.tinymce.min.js')}}"></script>
    <script src="{{asset('js/plugins/tinymce/tinymce.min.js')}}"></script>
    <script src="{{asset('js/plugins/tinymce/langs/de.js')}}"></script>
    <script>
        tinymce.init({
            selector: 'textarea',
            lang:'de',
            height: 400,
            menubar: true,
            plugins: [
                'advlist autolink lists link charmap',
                'searchreplace visualblocks code',
                'insertdatetime table paste code wordcount',
                'contextmenu',
            ],
            toolbar: 'undo redo  | bold italic backcolor forecolor  | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link ',
            contextmenu: " link paste inserttable | cell row column deletetable",
        });
    </script>
@endpush
