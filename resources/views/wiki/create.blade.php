@extends('layouts.app')

@section('content')

    <div class="container-fluid">
        <p>
            <a href="{{url('wiki')}}" class="btn btn-primary btn-link">zurück</a>
        </p>
        <div class="card">
            <form action="{{url('wiki')}}" method="post" class="form-horizontal"  enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="previous_version" value=" @if($site->text != "" and $site->previous_version == "") {{$site->id}} @else {{$site->previous_version}} @endif">
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
                    <div class="row">
                    @if($site->getMedia()->count() > 0)
                        <div class="col-9 border">
                    @else
                        <div class="col-12">
                    @endif
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
                                <div class="col-sm-12 col-md-12 col-lg-12">
                                    <label for="information">zusätzliche Dateien</label>
                                    <input type="file"  name="files[]" id="customFile" multiple>
                                </div>
                            </div>
                            <div class="form-row mt-2">
                                <button type="submit" class="btn btn-block btn-bg-gradient-x-blue-green">
                                    speichern
                                </button>
                            </div>
                        </div>
                    @if($site->getMedia()->count() > 0)

                        <div class="col-3">
                            <h5>Bilder löschen</h5>
                            <ul class="list-group">
                                @foreach($site->getMedia()->sortBy('name') as $media)
                                    <li class="list-group-item">
                                        <img class="mx-auto img-thumbnail" src="{{url('/image/'.$media->id)}}" style="max-height: 200px; max-width: 200px" >
                                        <a href="{{url('image/remove/wiki/'.$media->id)}}" class="card-link text-danger pull-right"><i class="fa fa-trash"></i> löschen</a>
                                    </li>

                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </form>
            </div>
        </div>
    </div>
@endsection


@push('css')

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/css/fileinput.min.css" media="all" rel="stylesheet" type="text/css" />

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
            width: '100%',
            menubar: true,
            autosave_ask_before_unload: true,
            autosave_interval: '40s',
            plugins: [
                'advlist anchor autolink lists link charmap',
                'searchreplace visualblocks code',
                'insertdatetime table paste code wordcount',
                'contextmenu autosave preview',
            ],
            link_list: [
                @foreach($sites as $link_site)
                    {title: '{{$link_site->title}}', value: '{{route("wiki", ['slug' => $link_site->slug])}}'},
                @endforeach
            ],
            toolbar: 'undo redo  | bold italic backcolor forecolor  | alignleft aligncenter alignright alignjustify | image anchor  bullist numlist outdent indent | removeformat | link | restoredraft | preview',
            contextmenu: " link paste inserttable | cell row column deletetable",
            relative_urls : false,
            a11y_advanced_options: true,
            document_base_url : 'http://{{config('app.url')}}/wiki/',
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
            'previewFileType': 'any',
            maxFileSize: '{{config('app.maxFileSize', 100)}}',
            'theme': "fas",
        });
    </script>


@endpush
