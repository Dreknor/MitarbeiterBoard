@extends('layouts.app')

@section('content')
    <a href="{{url(request()->segment(1).'/themes/recurring')}}" class="btn btn-primary btn-link">zurück</a>
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">
                bearbeite Thema
            </h5>
        </div>
        <div class="card-body border-top">
            <form method="post" class="form form-horizontal" action="{{url(request()->segment(1).'/themes/recurring/'.$theme->id)}}" id="editForm">
                @csrf
                @method('put')
                <div class="form-row">
                    <div class="col-sm-12 col-md-12 col-lg-6">
                        <label for="theme">Thema</label>
                        <input type="text" class="form-control" id="theme" name="theme" required autofocus value="{{old('theme', $theme->theme)}}">
                    </div>

                    <div class="col-sm-12 col-md-12 col-lg-3">
                            <label for="type">Monat</label>
                            <select name="month" id="month" class="custom-select" required>
                                <option disabled selected></option>
                                @foreach(config('config.months') as $key => $month)
                                    <option value="{{$key}}"  @if (old('month', $theme->month) == $key) selected @endif>{{$month}}</option>
                                @endforeach
                            </select>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-3">
                        <label for="type">Typ</label>
                        <select name="type" id="type" class="custom-select" required>
                            <option disabled></option>
                            @foreach($types as $type)
                                <option value="{{$type->id}}" @if (old('type', $theme->type_id) == $type->id) selected @endif>{{$type->type}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label for="goal">Ziel</label>
                    <input type="text" class="form-control" id="goal" name="goal" required value="{{old('goal', $theme->goal)}}">
                </div>
                <div class="form-row pt-1">
                    <label for="information">Informationen</label>
                    <textarea class="form-control w-100" id="information" name="information">
                        {{old('information', $theme->information)}}
                    </textarea>
                </div>
            </form>
        </div>
        <div class="card-footer">
            <button type="submit" form="editForm" class="btn btn-block btn-bg-gradient-x-blue-green">speichern</button>
        </div>
    </div>
        <div class="card">
            <div class="card-header">
                <h6>Thema löschen</h6>
            </div>
            <div class="card-footer">
                <form action="{{url(request()->segment(1).'/themes/recurring/'.$theme->id)}}" method="post" class="form-inline">
                    @method('delete')
                    @csrf
                    <button type="submit" class="btn btn-block btn-danger">
                        LÖSCHEN
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>
@stop

@push('js')

    <script src="{{asset('js/plugins/tinymce/jquery.tinymce.min.js')}}"></script>
    <script src="{{asset('js/plugins/tinymce/tinymce.min.js')}}"></script>
    <script src="{{asset('js/plugins/tinymce/langs/de.js')}}"></script>
    <script>
        tinymce.init({
            selector: 'textarea',
            lang:'de',
            height: 300,
            width: '100%',
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


