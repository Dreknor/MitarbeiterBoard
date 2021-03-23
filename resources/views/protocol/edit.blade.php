@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <a href="{{url(request()->segment(1).'/themes/'.$theme->id)}}" class="btn btn-primary btn-link">zurück</a>
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col">
                        <h5 class="card-title">
                            Protokoll zu "{{$theme->theme}}"
                        </h5>
                        <p class="small">
                            ACHTUNG: Es muss gespeichert werden
                        </p>
                    </div>
                    <div class="col d-sm-none d-md-block">
                            <div id="timer" class="timerDiv w-50 ">
                                <div class="row">
                                    <div id="hours" class="col"></div>
                                    <div id="minutes" class="col"></div>
                                    <div id="seconds" class="col"></div>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form action="{{url(request()->segment(1).'/protocols/'.$protocol->id)}}" method="post" class="form-horizontal"  enctype="multipart/form-data">
                    @csrf
                    @method('put')
                    <div class="form-row">
                        <div class="col-sm-12 col-md-12 col-lg-3">
                            <div class="container-fluid">
                                <div class="row">
                                    <label for="protocol">Protokoll</label>
                                </div>
                                <div class="row mt-1">

                                </div>
                            </div>

                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-9">
                            <textarea name="protocol"  class="form-control border-input" >
                                {{old('protocol', $protocol->protocol)}}
                            </textarea>
                        </div>
                    </div>
                    @can('complete theme')
                        <div class="form-row">
                            <div class="col-sm-12 col-md-12 col-lg-3">
                                <label for="completed">Thema abgeschlossen?</label>
                            </div>
                            <div class="col-sm-12 col-md-12 col-lg-9">
                                <input type="checkbox" name="completed" id="completed" value="1" class="custom-checkbox"> abgeschlossen
                            </div>
                        </div>
                    @endcan
                    <div class="form-row">
                        <div class="col-sm-12 col-md-12 col-lg-12">
                           <button type="submit" class="btn btn-success btn-block">Speichern</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop
@push('css')

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/css/fileinput.min.css" media="all" rel="stylesheet" type="text/css" />
    <link href="{{asset('css/timer.css')}}" media="all" rel="stylesheet" type="text/css" />

@endpush
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
            'previewFileType':'any',
            maxFileSize: {{config('app.maxFileSize')}},
            'theme': "fas",
        });
    </script>


@endpush
