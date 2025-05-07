@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-md-3">
                <div class="card">
                    <div class="card-header bg-gradient-directional-blue text-white">
                        <h6>
                            @can('edit tickets') Offene @else Meine @endcan Tickets
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            @forelse($tickets as $ticket)
                                <li class="list-group-item
                                @if(isset($show_ticket) and $show_ticket->id == $ticket->id)
                                    list-group-item-info
                                @endif
                                ">
                                    <div class="d-flex justify-content-between">
                                        <a href="{{ route('tickets.show', $ticket->id) }}">{{ $ticket->title }}</a>
                                    </div>

                                    @if($ticket->status == "open")
                                        <span class="badge pull-right p-1 m-1 badge-primary">{{ $ticket->category?->name }}</span>
                                        <span class="badge pull-right p-1 m-1 badge-gradient-x-blue-green">{{ $ticket->assigned?->name }}</span>
                                        <span class="badge pull-right p-1 m-1 @if($ticket->priority == "high") badge-danger @elseif($ticket->priority == "medium") badge-info @else badge-success @endif">{{ $ticket->priority }}</span>
                                    @endif

                                    <span class="pull-right m-1 p-1 badge @if($ticket->status == "open") badge-warning @elseif($ticket->status == "waiting") badge-info @endif">{{ $ticket->status }} @if($ticket->status == "waiting") bis {{$ticket->waiting_until->format('d.m.Y')}} @endif</span>
                                </li>
                            @empty
                                <li class="list-group-item">
                                    Es sind keine offenen Tickets vorhanden.
                                </li>
                            @endforelse

                        </ul>
                        <a href="{{ route('tickets.index') }}" class="btn btn-block btn-bg-gradient-x-blue-cyan">Neues Ticket erstellen</a>
                    </div>
                </div>
                @if(auth()->user()->pinned_tickets->count() > 0)
                    <div class="card mt-3">
                        <div class="card-header bg-gradient-directional-blue text-white">
                            <h6>
                                Gepinnte Tickets
                            </h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                @foreach(auth()->user()->pinned_tickets as $ticket)
                                    <li class="list-group-item">
                                        <a href="{{ route('tickets.show', $ticket->id) }}">{{ $ticket->title }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
            <div class="col-12 col-md-9">
                @if(isset($show_ticket))
                    @include('ticketsystem.show')
                @else
                    @include('ticketsystem.create')
                @endif
            </div>
        </div>
    </div>
@endsection


@push('css')

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/css/fileinput.min.css" media="all" rel="stylesheet" type="text/css" />

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
        });
    </script>
        <script src="{{asset('js/plugins/tinymce/jquery.tinymce.min.js')}}"></script>
        <script src="{{asset('js/plugins/tinymce/tinymce.min.js')}}"></script>
        <script src="{{asset('js/plugins/tinymce/langs/de.js')}}"></script>
        <script>

            document.querySelector('form').addEventListener('submit', function(event) {
                var commentField = document.querySelector('textarea');
                if (commentField.style.display === 'none') {
                    commentField.style.display = 'block';
                }
            });

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
                table_default_attributes: {
                    border: '1'
                }
            });
        </script>


@endpush
