@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <p>
            <a href="{{url('wiki')}}" class="btn btn-primary btn-link">zurück</a>
        </p>
        <div class="card">
            <form action="{{url('wiki')}}" method="post" class="form-horizontal">
                @csrf
                <input type="hidden" name="previous_version" value="{{$site->previous_version}}">
                <div class="card-header border-bottom">
                    <h5>
                        @if($site->text != "")
                            Seite bearbeiten
                        @else
                            neue Wiki-Seite
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <label class="w-100">
                            Titel
                            <input type="text" name="title" class="form-control" id="title" value="{{$site->title}}" maxlength="80"  @if($site->text != "") readonly @endif>
                        </label>
                    </div>
                    <div class="form-row mt-2">
                        <label class="w-100">
                            Inhalt
                        </label>
                        <textarea name="text">
                            {!! $site->text !!}
                        </textarea>
                    </div>
                    <div class="form-row mt-2">
                        <button type="submit" class="btn btn-block btn-bg-gradient-x-blue-green">
                            speichern
                        </button>
                    </div>
                </div>
            </form>
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
            height: 500,
            width: '100%',
            menubar: true,
            autosave_ask_before_unload: true,
            autosave_interval: '40s',
            plugins: [
                'advlist autolink lists link charmap',
                'searchreplace visualblocks code',
                'insertdatetime table paste code wordcount',
                'contextmenu autosave',
            ],
            toolbar: 'undo redo  | bold italic backcolor forecolor  | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link | restoredraft',
            contextmenu: " link paste inserttable | cell row column deletetable",
        });
    </script>





@endpush
