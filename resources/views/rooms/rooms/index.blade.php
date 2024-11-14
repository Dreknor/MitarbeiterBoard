@extends('layouts.app')

@section('content')

    <div class="container-fluid">
        @if(session('fehler') and count(session('fehler'))> 0)
            <div class="alert alert-danger">
                <ul>
                    @foreach(session('fehler') as $f)
                        <li>{{$f}}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="card">
            <div class="card-header">
                <h5>
                    vorhandene Räume
                </h5>
            </div>
            <div class="card-body">
                @if($rooms->count() > 0)
                    <table class="table table-striped w-100">
                    <thead>
                        <tr>
                            <th>

                            </th>
                            <th>
                                Name
                            </th>
                            <th>
                                Raum-Nummer
                            </th>
                            <th>
                                Aktionen
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rooms as $room)
                            <tr >
                                @if($room->availability)
                                    <td>
                                        <div class="text-success">
                                            frei
                                        </div>
                                    </td>
                                @else
                                    <td>
                                        <div class="text-danger">
                                            belegt {{$room->availability}}
                                        </div>

                                    </td>
                                @endif
                                <td>
                                    {{$room->name}}
                                </td>
                                <td>
                                    {{$room->room_number}}
                                </td>
                                <td>
                                    <a href="{{url('rooms/rooms/'.$room->id.'')}}" class="btn btn-bg-gradient-x-blue-purple-1 btn-sm">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    @can('manage rooms')
                                        <a href="{{url('rooms/rooms/'.$room->id.'/edit')}}" class="btn btn-sm btn-bg-gradient-x-orange-yellow">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endcan
                                    <!--
                                    <a href="{{url('rooms/rooms/'.$room->id.'/export')}}" class="btn btn-bg-gradient-x-blue-green btn-sm">
                                        <i class="fa fa-file-export"></i>
                                    </a>
                                    -->

                                    @if($room->bookings->count() == 0)
                                        <button class="btn btn-sm btn-bg-gradient-x-red-pink" type="submit" title="Raum löschen" form="deleteForm_{{$room->id}}">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                            <form method="post" id="deleteForm_{{$room->id}}" action="{{url('rooms/rooms/'.$room->id)}}" class="form-inline m-1">
                                                @csrf
                                                @method('delete')
                                            </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                    <p>Es wurden noch keine Räume angelegt</p>
                @endif
            </div>

            @can('manage rooms')
                <div class="card-footer border-top">
                <form action="{{url('rooms/rooms')}}" method="post" class="form-horizontal">
                    @csrf
                    <div class="form-row mb-2">
                        <label class="label w-100">
                            Name
                            <input class="form-control" type="text" name="name" required>
                        </label>
                    </div>
                    <div class="form-row mb-2">
                        <label class="label w-100">
                            Raumnummer
                            <input class="form-control" type="text" name="room_number">
                        </label>
                    </div>
                    <div class="form-row">
                            <button class="btn btn-success btn-bg-gradient-x-blue-green btn-block" type="submit">
                                Raum erstellen
                            </button>
                    </div>
                </form>
            </div>
        </div>

                @include('rooms.rooms.import')
            @endcan
        </div>
    </div>
@endsection

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

        $("#file").fileinput({
            'showUpload':false,
            'previewFileType':'any',
            maxFileSize: {{config('app.maxFileSize')}},
            'theme': "fas",
        });
    </script>


@endpush
@push('css')

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/css/fileinput.min.css" media="all" rel="stylesheet" type="text/css" />

@endpush
