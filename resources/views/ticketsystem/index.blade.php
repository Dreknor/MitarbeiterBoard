@extends('layouts.app')

@section('content')
    @php $listQuery = request()->getQueryString(); @endphp
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-lg-4 col-xl-3">
                @if($stats)
                    <div class="d-flex flex-wrap mb-2">
                        <a href="{{ route('tickets.index') }}" class="badge badge-primary p-2 mr-1 mb-1">{{ $stats['open'] }} offen</a>
                        <a href="{{ route('tickets.index', ['scope' => 'unassigned']) }}" class="badge badge-warning p-2 mr-1 mb-1">{{ $stats['unassigned'] }} nicht zugewiesen</a>
                        <a href="{{ route('tickets.index', ['scope' => 'mine']) }}" class="badge badge-info p-2 mr-1 mb-1">{{ $stats['mine'] }} mir zugewiesen</a>
                        @if($stats['overdue'] > 0)
                            <a href="{{ route('tickets.index', ['status' => 'waiting']) }}" class="badge badge-danger p-2 mr-1 mb-1">{{ $stats['overdue'] }} Wartezeit abgelaufen</a>
                        @endif
                    </div>
                @endif

                <div class="card">
                    <div class="card-header bg-gradient-directional-blue text-white">
                        <h6 class="mb-0">
                            @can('edit tickets') Offene @else Meine offenen @endcan Tickets
                            <span class="badge badge-light float-right">{{ $tickets->count() }}</span>
                        </h6>
                    </div>
                    <div class="card-body p-2">
                        <form method="get" action="{{ route('tickets.index') }}" class="mb-2">
                            <div class="input-group input-group-sm mb-1">
                                <input type="search" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Suche (Titel, Text, #Nr.)">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="submit"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                            <div class="form-row">
                                @can('edit tickets')
                                    <div class="col-6 mb-1">
                                        <select name="scope" class="form-control form-control-sm" onchange="this.form.submit()">
                                            <option value="all" @selected($filters['scope'] == 'all')>Alle</option>
                                            <option value="mine" @selected($filters['scope'] == 'mine')>Mir zugewiesen</option>
                                            <option value="unassigned" @selected($filters['scope'] == 'unassigned')>Nicht zugewiesen</option>
                                            <option value="created" @selected($filters['scope'] == 'created')>Von mir erstellt</option>
                                        </select>
                                    </div>
                                @endcan
                                <div class="col-6 mb-1">
                                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                        <option value="">Status: alle</option>
                                        <option value="open" @selected($filters['status'] == 'open')>offen</option>
                                        <option value="waiting" @selected($filters['status'] == 'waiting')>wartend</option>
                                    </select>
                                </div>
                                <div class="col-6 mb-1">
                                    <select name="priority" class="form-control form-control-sm" onchange="this.form.submit()">
                                        <option value="">Priorität: alle</option>
                                        @foreach(\App\Models\Ticket::PRIORITY_LABELS as $value => $label)
                                            <option value="{{ $value }}" @selected($filters['priority'] == $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if($categories->isNotEmpty())
                                    <div class="col-6 mb-1">
                                        <select name="category" class="form-control form-control-sm" onchange="this.form.submit()">
                                            <option value="">Kategorie: alle</option>
                                            @foreach($categories as $category)
                                                <option value="{{ $category->id }}" @selected($filters['category'] == $category->id)>{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="col-6 mb-1">
                                    <select name="sort" class="form-control form-control-sm" onchange="this.form.submit()">
                                        <option value="activity" @selected($filters['sort'] == 'activity')>Letzte Aktivität</option>
                                        <option value="priority" @selected($filters['sort'] == 'priority')>Priorität</option>
                                        <option value="created" @selected($filters['sort'] == 'created')>Neueste zuerst</option>
                                    </select>
                                </div>
                            </div>
                            @if($listQuery)
                                <a href="{{ route('tickets.index') }}" class="small">Filter zurücksetzen</a>
                            @endif
                        </form>

                        <ul class="list-group list-group-flush">
                            @forelse($tickets as $ticket)
                                <li class="list-group-item px-2 @if($show_ticket && $show_ticket->id == $ticket->id) list-group-item-info @endif">
                                    <a href="{{ route('tickets.show', $ticket->id) }}{{ $listQuery ? '?'.$listQuery : '' }}" class="d-block font-weight-bold">
                                        <span class="text-muted small">#{{ $ticket->id }}</span> {{ $ticket->title }}
                                    </a>
                                    <div class="mt-1">
                                        @include('ticketsystem.partials.badges', ['ticket' => $ticket])
                                    </div>
                                    <div class="small text-muted mt-1 d-flex justify-content-between">
                                        <span>
                                            @if($ticket->assigned)
                                                <i class="fa fa-user"></i> {{ $ticket->assigned->name }}
                                            @else
                                                <i class="fa fa-user-slash"></i> nicht zugewiesen
                                            @endif
                                        </span>
                                        <span title="Letzte Aktivität">
                                            <i class="fa fa-comments"></i> {{ $ticket->comments_count }}
                                            · {{ $ticket->last_activity?->diffForHumans() }}
                                        </span>
                                    </div>
                                </li>
                            @empty
                                <li class="list-group-item">
                                    @if($listQuery)
                                        Keine Tickets für diese Filter gefunden.
                                    @else
                                        Es sind keine offenen Tickets vorhanden.
                                    @endif
                                </li>
                            @endforelse
                        </ul>
                        <a href="{{ route('tickets.index') }}" class="btn btn-block btn-bg-gradient-x-blue-cyan mt-2">
                            <i class="fa fa-plus"></i> Neues Ticket erstellen
                        </a>
                    </div>
                </div>

                @if($pinned->isNotEmpty())
                    <div class="card mt-3">
                        <div class="card-header bg-gradient-directional-blue text-white">
                            <h6 class="mb-0"><i class="fa fa-thumbtack"></i> Gepinnte Tickets</h6>
                        </div>
                        <div class="card-body p-2">
                            <ul class="list-group list-group-flush">
                                @foreach($pinned as $ticket)
                                    <li class="list-group-item px-2">
                                        <a href="{{ route('tickets.show', $ticket->id) }}">#{{ $ticket->id }} {{ $ticket->title }}</a>
                                        @if($ticket->isClosed())
                                            <span class="badge badge-secondary">geschlossen</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
            <div class="col-12 col-lg-8 col-xl-9 mt-3 mt-lg-0">
                @if($show_ticket)
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
    <!-- piexif.min.js wird für die automatische Ausrichtung von Bildern benötigt und muss vor fileinput.min.js geladen werden -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/piexif.min.js" type="text/javascript"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/sortable.min.js" type="text/javascript"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/purify.min.js" type="text/javascript"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/fileinput.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/themes/fas/theme.min.js"></script>

    <script>
        $("#customFile").fileinput({
            showUpload: false,
            maxFileCount: 10,
            maxFileSize: 20480,
        });
    </script>
    <script src="{{asset('js/plugins/tinymce/jquery.tinymce.min.js')}}"></script>
    <script src="{{asset('js/plugins/tinymce/tinymce.min.js')}}"></script>
    <script src="{{asset('js/plugins/tinymce/langs/de.js')}}"></script>
    <script>
        tinymce.init({
            selector: 'textarea.ticket-editor',
            lang: 'de',
            height: 300,
            width: '100%',
            menubar: false,
            plugins: [
                'advlist autolink lists link charmap',
                'searchreplace visualblocks code',
                'insertdatetime table paste code wordcount',
                'contextmenu',
            ],
            toolbar: 'undo redo | bold italic backcolor forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link table',
            contextmenu: "link paste inserttable | cell row column deletetable",
            table_default_attributes: {
                border: '1'
            }
        });

        // TinyMCE versteckt das Textfeld – leere Eingaben vor dem Absenden abfangen
        document.querySelectorAll('form.ticket-editor-form').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                tinymce.triggerSave();
                var field = form.querySelector('textarea.ticket-editor');
                if (field && field.value.replace(/<[^>]*>|&nbsp;|\s/g, '') === '') {
                    event.preventDefault();
                    alert('Bitte einen Text eingeben.');
                }
            });
        });
    </script>
@endpush
