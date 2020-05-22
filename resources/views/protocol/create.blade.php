@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <a href="{{url(request()->segment(1).'/themes')}}" class="btn btn-primary btn-link">zurück</a>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    Protokoll zu "{{$theme->theme}}"
                </h5>
                <p class="small">
                    ACHTUNG: Es muss alle 5 Minuten gespeichert werden
                </p>
            </div>
            <div class="card-body">
                <form action="{{url('protocols/'.$theme->id)}}" method="post" class="form-horizontal">
                    @csrf
                    <div class="form-row">
                        <div class="col-sm-12 col-md-12 col-lg-3">
                            <label for="protocol">Protokoll</label>
                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-9">
                            <textarea name="protocol"  class="form-control border-input" >
                                {{old('protocol')}}
                            </textarea>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-sm-12 col-md-12 col-lg-3">
                            <label for="completed">Thema abgeschlossen?</label>
                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-9">
                            <input type="checkbox" name="completed" id="completed" value="1" class="custom-checkbox"> abgeschlossen
                        </div>

                    </div>
                    <div class="form-row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-success btn-block">speichern</button>
                        </div>

                    </div>
                </form>
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
            height: 500,
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
