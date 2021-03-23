@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <a href="{{url(request()->segment(1).'/themes')}}" class="btn btn-primary btn-link">zurück</a>
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
                    <div class="col">
                       <a class="btn btn-sm btn-info pull-right" id="newTaskLink" data-toggle="modal" data-target="#taskModal">
                           Aufgabe erstellen
                       </a>
                    </div>
                    <div class="col d-sm-none d-md-block">
                            <div id="timer" class="timerDiv w-75 ">
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
                <form action="{{url(request()->segment(1).'/protocols/'.$theme->id)}}" method="post" class="form-horizontal"  enctype="multipart/form-data">
                    @csrf
                    <div class="form-row">
                        <div class="col-sm-12 col-md-12 col-lg-3">
                            <div class="container-fluid">
                                <div class="row">
                                    <label for="protocol">Protokoll</label>
                                </div>
                            </div>

                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-9">
                            <textarea name="protocol"  class="form-control border-input" >
                                {{old('protocol')}}
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

                    <div class="form-row pt-2">
                        <div class="col-sm-12 col-md-12 col-lg-3">
                            <div class="container-fluid">
                                <div class="row">
                                    <label for="information">zusätzliche Dateien</label>
                                </div>
                            </div>

                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-9">
                                <input type="file"  name="files[]" id="customFile" multiple>
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

@push('modals')
    <div class="modal fade" id="taskModal" tabindex="-1" role="dialog"  aria-hidden="true">
        <div class="modal-dialog modal-lg " role="document">
            <div class="modal-content">
                <div class="modal-header" id="modalHeader">
                    <h5 class="modal-title">Aufgabe hinzufügen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body"  id="modalBody">
                    <form action="" method="post" class="form-horizontal" id="taskForm">
                        @csrf
                        <div class="form-row p-2">
                            <label for="date">zu erledigen bis...</label>
                            <input type="date" name="date" min="{{\Carbon\Carbon::now()->addDay()->format('Y-m-d')}}" value="{{old('date')}}" class="form-control" required autofocus>
                        </div>
                        <div class="form-row p-2">
                            <label for="task">Aufgabe</label>
                            <input type="text" name="task" min="{{\Carbon\Carbon::now()->addDay()->format('Y-m-d')}}" value="{{old('task')}}" class="form-control" required>
                        </div>
                        <div class="form-row p-2">
                            <label for="taskable">Aufgabe für ...</label>
                            <select class="custom-select" name="taskable">
                                <option value="{{request()->segment(1)}}">Gruppe {{request()->segment(1)}}</option>
                                @foreach($theme->group->users as $user)
                                    <option value="{{$user->id}}">{{$user->name}}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="submit"  form="taskForm" class="btn btn-primary" id="submitTask">Speichern</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="submitTaskModal" tabindex="-1" role="dialog"  aria-hidden="true">
        <div class="modal-dialog modal-lg " role="document">
            <div class="modal-content">
                <div class="modal-header bg-success" >
                    <h5 class="modal-title">Aufgabe erstellt</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>
                        Aufgabe gespeichert
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>

                </div>
            </div>
        </div>
    </div>
@endpush


@push('css')

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/css/fileinput.min.css" media="all" rel="stylesheet" type="text/css" />
    <link href="{{asset('css/timer.css')}}" media="all" rel="stylesheet" type="text/css" />

@endpush
@push('js')
    <script>
        document.querySelector('#submitTask').addEventListener('click', function(e) {
            e.preventDefault();
            $('#submitTask').hide();

            let url = "{{url(request()->segment(1).'/'.$theme->id.'/tasks')}}";
            $.ajax({
                type: 'POST',
                url: url,
                data: $("#taskForm").serialize(),
                success: function (response) {
                    $('#taskModal').modal('toggle');
                    $('#submitTask').toogle();
                    document.getElementById("#taskForm").reset();
                    $('#submitTaskModal').modal('toggle');
                },
                error: function (error) {
                    $('#modalHeader').addClass('bg-danger');
                }
            });
        });
    </script>


    @if($theme->date->startOfDay()->equalTo(\Carbon\Carbon::now()->startOfDay()))
        <script>
            function makeTimer() {

                let outline =''
                var endTime = new Date("{{\Carbon\Carbon::now()->addMinutes($theme->duration)->format('Y-m-d H:i:s')}}");
                endTime = (Date.parse(endTime) / 1000);

                var now = new Date();
                now = (Date.parse(now) / 1000);

                var timeLeft = endTime - now;

                if (timeLeft > 0){
                    var hours = Math.floor((timeLeft) / 3600);
                    var minutes = Math.floor((timeLeft - (hours * 3600 )) / 60);
                    var seconds = Math.floor((timeLeft  - (hours * 3600) - (minutes * 60)));
                } else {
                    timeLeft = now - endTime;
                    var hours = Math.floor((timeLeft) / 3600);
                    var minutes = Math.floor((timeLeft - (hours * 3600 )) / 60);
                    var seconds = Math.floor((timeLeft  - (hours * 3600) - (minutes * 60)));

                    if(!$("#timer").hasClass("btn-outline-danger")){
                        $("#timer").addClass("btn-outline-danger");
                    }
                }


                if (hours < "10") { hours = "0" + hours; }
                if (minutes < "10") { minutes = "0" + minutes; }
                if (seconds < "10") { seconds = "0" + seconds; }

                $("#hours").html(hours + "<span class='timerSpan'> Stunden</span>");
                $("#minutes").html(minutes + "<span class='timerSpan'> Minuten</span>");
                $("#seconds").html(seconds + "<span class='timerSpan'> Sekunden</span>");


            }

            setInterval(function() { makeTimer(); }, 1000);
        </script>
    @endif
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
