@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <a href="{{url('home')}}" class="btn btn-bg-gradient-x-blue-cyan">zurück</a>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    bearbeite Nachricht
                </h5>
            </div>
            <div class="card-body border-top">
                <form method="post" class="form form-horizontal" action="{{url('posts/'.$post->id)}}" id="createForm"  enctype="multipart/form-data">
                    @csrf
                    @method('put')
                    <div class="form-row pt-2">
                        <div class="col-sm-12 col-md-12 col-lg-9">
                            <label for="theme">Überschrift</label>
                            <input type="text" class="form-control" id="header" name="header" required autofocus value="{{old('header', $post->header)}}">
                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-3">
                            <label for="type">Veröffentlicht?</label>
                            <select name="released" id="type" class="custom-select" required>
                                <option value="1" @if(old('released', $post->released) == 1) selected @endif>ja</option>
                                <option value="0" @if(old('released', $post->released) == 0) selected @endif>nein</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row pt-2">
                        <div class="col-sm-12 col-md-12 col-lg-9">
                            <label for="information">Text</label>
                            <textarea class="form-control" id="text" name="text" required>
                                {{old('text', $post->text)}}
                            </textarea>
                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-3">
                            <label for="type">Gruppen?</label>
                            <div class="container-fluid">
                                @foreach(auth()->user()->groups_rel as $group)
                                    <div class="row">
                                        <div class="col-12">
                                            <label class="w-100 ">
                                                <input type="checkbox" class="custom-checkbox" name="groups[]" value="{{$group->id}}" @if($post->groups->contains('name',$group->name)) checked @endif>
                                                {{$group->name}}

                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                        </div>
                    </div>
                    <div class="form-row pt-2">
                        <div class="col-sm-12 col-md-12 col-lg-12">
                            <label for="information">zusätzliche Dateien</label>
                            <input type="file"  name="files[]" id="customFile" multiple>
                        </div>
                    </div>
                </form>
            </div>
            @if($post->getMedia('images')->count() > 0 or $post->getMedia('files')->count() > 0)
                <div class="card-footer">
                    <div class="row">
                        <div class="col-sm-12 col-md-12 col-lg-12">
                            <ul class="list-group">
                                @foreach($post->getMedia('files') as $file)
                                    <li class="list-group-item">
                                        {{$file->name}}
                                        <form action="{{url('/image/'.$file->id)}}" method="post" class="" id="form{{$file->id}}">
                                            @csrf
                                            @method('delete')
                                            <input type="hidden" name="post_id" value="{{$post->id}}">
                                            <button type="submit" class="btn btn-link text-danger pull-right">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </li>
                                @endforeach
                                @foreach($post->getMedia('images') as $file)
                                    <li class="list-group-item">
                                        <img src="{{url('/image/'.$file->id)}}" alt="{{$file->name}}" class="img-fluid" style="max-height: 200px;">
                                        <form action="{{url('/image/'.$file->id)}}" method="post" class="" id="form{{$file->id}}">
                                            @csrf
                                            @method('delete')
                                            <input type="hidden" name="post_id" value="{{$post->id}}">
                                            <button type="submit" class="btn btn-link text-danger pull-right">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                    </div>
                </div>
            @endif
            <div class="card-footer">
                <button type="submit" form="createForm" class="btn bg-gradient-x-success btn-block">speichern</button>
            </div>
        </div>
    </div>
@stop


@push('css')

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/css/fileinput.min.css" media="all" rel="stylesheet" type="text/css" />

@endpush


@push('js')
    <script>
        $(function () {
            $('[data-toggle="popover"]').popover()
        })
    </script>

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
