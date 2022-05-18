@extends('layouts.app')
@section('content')
    <div class="container-fluid">

        <a href="{{url($task->wprow->wochenplan->group->name).'/wochenplan/'.$task->wprow->wochenplan->id}}" class="btn btn-primary">zurück</a>

        <div class="card">
            <div class="card-header">
                <h6>
                   Aufgabe bearbeiten
                </h6>
            </div>
            <div class="card-body">
                <form action="{{url('wptask/'.$task->id.'/edit')}}" method="post" class="form-horizontal">
                    @csrf
                    @method('put')
                    <div class="form-row mb-2">
                        <div class="col-sm-12 col-md-12 col-lg-3">
                            <div class="container-fluid">
                                <div class="row">
                                    <label for="duration">Dauer</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-9">
                            <input type="text" name="duration" id="duration" class="form-control" value="{{$task->duration}}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-sm-12 col-md-12 col-lg-3">
                            <div class="container-fluid">
                                <div class="row">
                                    <label for="protocol">Aufgabe</label>
                                </div>
                            </div>

                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-9">
                            <textarea name="task"  id="task"   class="form-control border-input" >
                                {{old('task', $task->task)}}
                            </textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success btn-block">Aufgabe speichern</button>
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



    <script>
        tinymce.init({
            selector: 'textarea',
            lang:'de',
            inline_styles : true,
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
    <script>
        // initialize with defaults

        $("#customFile").fileinput({
            'showUpload':false,
            'previewFileType':'any',
            'maxFileSize': {{config('app.maxFileSize')}},
            'theme': "fas",
        });
    </script>
@endpush
