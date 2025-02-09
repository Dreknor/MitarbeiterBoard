
@can('edit tickets')
    <div class="floating-button-menu menu-off">
        <div class="floating-button-menu-links">
            <a href="{{ route('tickets.pin', $show_ticket->id) }}" class="text-primary floating-button-menu-link">
                <i class="fa fa-thumbtack"></i>  @if(auth()->user()->pinned_tickets->contains($show_ticket->id)) Lösen @else Anpinnen @endif
            </a>

            @if($show_ticket->status != 'closed')
                <a href="{{ route('tickets.close', $show_ticket->id) }}" class="text-danger floating-button-menu-link ">
                    <i class="fa fa-check"></i> Schließen
                </a>
                    <div class="dropdown">
                        <a class="dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            @if($show_ticket->assigned != null)
                                zugewiesen: {{$show_ticket->assigned->name}}
                            @else
                                Zuweisen an
                            @endif
                        </a>
                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            <span class="dropdown-item bg-gradient-directional-amber text-white">Zuweisen an</span>
                            @foreach($assignable as $user)
                                @if($show_ticket->assigned_to == null and $show_ticket->assigned_to != $user->id)
                                    <a class="dropdown-item" href="{{ route('tickets.assign', [$show_ticket->id, $user->id]) }}">{{ $user->name }}</a>
                                @endif
                            @endforeach
                        </div>
                    </div>
            @endif
        </div>
        <div class="floating-button-menu-label"><i class="fa fa-bars"></i></div>
    </div>
    <div class="floating-button-menu-close"></div>
@endcan
<div class="card">
    <div class="card-header bg-gradient-directional-blue text-white">
        <span class="badge badge-info">{{ $show_ticket->category?->name }}</span>
        <span class="badge badge-warning">{{ $show_ticket->priority}}</span>
        <h5>
            Ticket: {{ $show_ticket->title }}
        </h5>
        <p >
            Erstellt am {{ $show_ticket->created_at->format('d.m.Y H:i') }} von {{ $show_ticket->user->name }}
        </p>

    </div>
    <div class="card-body">
        <p>{!! $show_ticket->description  !!} </p>
    </div>
    @if($show_ticket->getMedia()->count() > 0)
        <div class="card-footer">
            {{-- Dateien anzeigen --}}

                <h6>Dateien</h6>
                <ul class="list-group">
                    @foreach($show_ticket->files as $file)
                        <li class="list-group-item">
                            <a href="{{ route('tickets.download', $file->id) }}">{{ $file->name }}</a>
                        </li>
                    @endforeach
                </ul>

        </div>
    @endif
    @if($show_ticket->status != 'closed')
        <div class="card-footer border-top">
        <form action="{{ route('tickets.comments.store', $show_ticket->id) }}" method="post">
            @csrf
            <div class="form-group">
                <label for="comments">Kommentar</label>
                <textarea class="form-control" id="comment" name="comment"></textarea>
            </div>
            @can('edit tickets')
                <div class="row">
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label for="internal">Sichtbarkeit</label>
                            <select class="form-control" id="internal" name="internal" required>
                                <option value="0">Öffentlich</option>
                                <option value="1">Intern</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label for="status">Warten bis</label>
                            <input type="date" class="form-control" id="waiting_until" name="waiting_until">
                        </div>
                    </div>
                </div>

            @endcan
            <button type="submit" class="btn btn-primary">Kommentar hinzufügen</button>
        </form>
    </div>
    @endif
    @forelse($show_ticket->comments->sortByDesc('created_at') as $comment)
        <div class="card-footer  @if($comment->internal) bg-light border border-secondary @endif">
            @if($comment->internal)
                <span class="badge badge-warning">Intern</span>
            @endif
            <p>
                <strong>{{ $comment->user->name }}</strong> schrieb am {{ $comment->created_at->format('d.m.Y H:i') }}:
            </p>
            <p>
                {!! $comment->comment !!}
            </p>
        </div>
    @empty
        <div class="card-footer bg-gradient-directional-grey-blue text-white">
            <p>
                Das Ticket wurde noch nicht bearbeitet.
            </p>
        </div>
    @endforelse
</div>

@push('js')


    <script>
        $( ".menu-off" ).click(function() {
            $( this ).removeClass( "menu-off" );
            $( this ).addClass( "menu-on" );
            $('.floating-button-menu-close').addClass('menu-on');
        });
        $('.floating-button-menu-close').click(function(){
            $( this ).addClass( "menu-off" );
            $( this ).removeClass( "menu-on" );
            $('.floating-button-menu').toggleClass('menu-on');
        });



    </script>

@endpush

@push('css')
    <link href="{{asset('css/floating_menu.css')}}" rel="stylesheet">

@endpush
