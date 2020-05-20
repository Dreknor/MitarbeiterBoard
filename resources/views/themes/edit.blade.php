@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">
                bearbeite Thema
            </h5>
        </div>
        <div class="card-body border-top">
            <form method="post" class="form form-horizontal" action="{{url('themes/'.$theme->id)}}" id="editForm"  enctype="multipart/form-data">
                @csrf
                @method('put')
                <div class="form-row">
                    <div class="col-sm-12 col-md-12 col-lg-4">
                        <label for="theme">Thema</label>
                        <input type="text" class="form-control" id="theme" name="theme" required autofocus value="{{old('theme', $theme->theme)}}" @if ($theme->priority) readonly @endif>
                    </div>

                    <div class="col-sm-12 col-md-12 col-lg-3">
                        <label for="theme">Datum</label>
                        <input type="date" class="form-control" id="date" name="date" required  value="{{old('date', $theme->date->format('Y-m-d'))}}">
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-3">
                        <label for="type">Typ</label>
                        <select name="type" id="type" class="custom-select" required>
                            <option disabled></option>
                            @foreach($types as $type)
                                <option value="{{$type->id}}" data-needsprotocol="{{$type->needsProtocol}}" @if (old('type', $theme->type_id) == $type->id) selected @endif>{{$type->type}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-2">
                        <label for="duration">Dauer</label>
                        <input type="number" class="form-control" id="duration" name="duration" required min="5" max="240" step="5" value="{{old('duration', $theme->duration)}}">
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
                <div class="form-row pt-2">
                    <div class="col-sm-12 col-md-12 col-lg-12">
                        <label for="information">zusätzliche Dateien</label>
                        <input type="file"  name="files[]" id="customFile" multiple>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer">
            <button type="submit" form="editForm" class="btn btn-warnig btn-block">speichern</button>
        </div>
    </div>
</div>
@stop

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
            maxFileSize: 3000,
            'theme': "fas",
        });
    </script>


@endpush
