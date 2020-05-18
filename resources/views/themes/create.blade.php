@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <a href="{{url('themes')}}" class="btn btn-primary btn-link">zurück</a>
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">
                neues Thema
            </h5>
        </div>
        <div class="card-body border-top">
            <form method="post" class="form form-horizontal" action="{{url('themes')}}" id="createForm">
                @csrf
                <div class="form-row pt-2">
                    <div class="col-sm-12 col-md-12 col-lg-4">
                        <label for="theme">Thema</label>
                        <input type="text" class="form-control" id="theme" name="theme" required autofocus value="{{old('theme')}}">
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-4">
                        <label for="type">Typ</label>
                        <select name="type" id="type" class="custom-select" required>
                            <option disabled></option>
                            @foreach($types as $type)
                                <option value="{{$type->id}}" data-needsprotocol="{{$type->needsProtocol}}" @if (old('type') == $type->id) selected @endif>{{$type->type}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-4">
                        <label for="duration">Dauer</label>
                        <input type="number" class="form-control" id="duration" name="duration" required min="5" max="240" step="5" value="{{old('duration')}}">
                    </div>
                </div>
                <div class="form-row pt-2">
                    <div class="col-sm-12 col-md-12 col-lg-12">
                        <label for="goal">Ziel</label>
                        <input type="text" class="form-control" id="goal" name="goal" required value="{{old('goal')}}">
                    </div>
                </div>
                <div class="form-row pt-2">
                    <div class="col-sm-12 col-md-12 col-lg-12">
                        <label for="information">Informationen</label>
                        <textarea class="form-control" id="information" name="information">
                            {{old('information')}}
                        </textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer">
            <button type="submit" form="createForm" class="btn btn-success btn-block">speichern</button>
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