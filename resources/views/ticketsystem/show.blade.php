@php
    $canManage = auth()->user()->can('manage', $show_ticket);
    $isPinned = auth()->user()->pinned_tickets->contains($show_ticket->id);
@endphp

<div class="card mb-3">
    <div class="card-header bg-gradient-directional-blue text-white">
        <div class="d-flex justify-content-between flex-wrap">
            <div class="mb-1">
                @include('ticketsystem.partials.badges', ['ticket' => $show_ticket])
            </div>
            <div class="mb-1">
                <form action="{{ route('tickets.pin', $show_ticket) }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-light" title="{{ $isPinned ? 'Lösen' : 'Anpinnen' }}">
                        <i class="fa fa-thumbtack @if(!$isPinned) text-muted @endif"></i> {{ $isPinned ? 'Lösen' : 'Anpinnen' }}
                    </button>
                </form>
                @can('reopen', $show_ticket)
                    <form action="{{ route('tickets.reopen', $show_ticket) }}" method="post" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-undo"></i> Wieder öffnen</button>
                    </form>
                @endcan
                @can('close', $show_ticket)
                    <button type="button" class="btn btn-sm btn-success" data-toggle="collapse" data-target="#closeTicketForm" aria-expanded="false">
                        <i class="fa fa-check"></i> Schließen
                    </button>
                @endcan
            </div>
        </div>
        <h5 class="mt-1 mb-1">
            <span class="text-white-50">#{{ $show_ticket->id }}</span> {{ $show_ticket->title }}
        </h5>
        <p class="mb-0 small">
            Erstellt am {{ $show_ticket->created_at->format('d.m.Y H:i') }} von {{ $show_ticket->user?->name ?? 'unbekannt' }}
            · @if($show_ticket->assigned) zugewiesen an {{ $show_ticket->assigned->name }} @else nicht zugewiesen @endif
            @if($show_ticket->isWaiting() && $show_ticket->waiting_until)
                · wartet auf Rückmeldung bis {{ $show_ticket->waiting_until->format('d.m.Y') }}
            @endif
            @if($show_ticket->isClosed() && $show_ticket->closed_at)
                · geschlossen am {{ $show_ticket->closed_at->format('d.m.Y H:i') }}
                @if($show_ticket->closedBy) von {{ $show_ticket->closedBy->name }} @else (automatisch) @endif
            @endif
        </p>
    </div>

    @can('close', $show_ticket)
        <div class="collapse" id="closeTicketForm">
            <div class="card-body border-bottom bg-light">
                <form action="{{ route('tickets.close', $show_ticket) }}" method="post">
                    @csrf
                    <div class="form-group mb-2">
                        <label for="close_reason" class="small mb-1">Abschlussnotiz (optional, für alle sichtbar)</label>
                        <input type="text" name="reason" id="close_reason" class="form-control form-control-sm" maxlength="1000"
                               placeholder="z. B. Problem behoben, Gerät getauscht …">
                    </div>
                    <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-check"></i> Ticket schließen</button>
                </form>
            </div>
        </div>
    @endcan

    @if($canManage && !$show_ticket->isClosed())
        <div class="card-body border-bottom bg-light py-2">
            <div class="form-row align-items-end">
                <div class="col-12 col-md-6 mb-2 mb-md-0">
                    <form action="{{ route('tickets.assign', $show_ticket) }}" method="post">
                        @csrf
                        <label for="assign_user" class="small mb-1">Zuständig</label>
                        <div class="input-group input-group-sm">
                            <select name="user_id" id="assign_user" class="form-control">
                                <option value="">– niemand –</option>
                                @foreach($assignable as $user)
                                    <option value="{{ $user->id }}" @selected($show_ticket->assigned_to == $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-outline-primary">Zuweisen</button>
                                @if($show_ticket->assigned_to != auth()->id())
                                    <button type="submit" name="user_id" value="{{ auth()->id() }}" class="btn btn-primary">Mir</button>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-12 col-md-6">
                    <form action="{{ route('tickets.update', $show_ticket) }}" method="post">
                        @csrf
                        @method('PATCH')
                        <label class="small mb-1">Kategorie / Priorität</label>
                        <div class="input-group input-group-sm">
                            <select name="category_id" class="form-control" aria-label="Kategorie">
                                <option value="">– keine –</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected($show_ticket->category_id == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <select name="priority" class="form-control" aria-label="Priorität">
                                @foreach(\App\Models\Ticket::PRIORITY_LABELS as $value => $label)
                                    <option value="{{ $value }}" @selected($show_ticket->priority == $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-outline-primary">Speichern</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="card-body">
        {!! $show_ticket->description_html !!}
    </div>

    @if($show_ticket->getMedia('ticket_files')->isNotEmpty())
        <div class="card-footer">
            <h6>Dateien</h6>
            <ul class="list-unstyled mb-0">
                @foreach($show_ticket->getMedia('ticket_files') as $file)
                    <li>
                        <a href="{{ route('tickets.files', [$show_ticket, $file]) }}" target="_blank" rel="noopener">
                            <i class="fa fa-paperclip"></i> {{ $file->file_name }}
                        </a>
                        <small class="text-muted">({{ $file->human_readable_size }})</small>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @can('comment', $show_ticket)
        <div class="card-footer border-top">
            <form action="{{ route('tickets.comments.store', $show_ticket) }}" method="post" enctype="multipart/form-data" class="ticket-editor-form">
                @csrf
                <div class="form-group">
                    <label for="comment">
                        @if($canManage) Antwort / Notiz @else Antwort @endif
                    </label>
                    <textarea class="form-control ticket-editor" id="comment" name="comment">{{ old('comment') }}</textarea>
                    @error('comment')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="form-row">
                    <div class="col-12 @if($canManage) col-md-4 @endif">
                        <div class="form-group">
                            <label for="comment_files" class="small">Dateien anhängen</label>
                            <input type="file" class="form-control-file" id="comment_files" name="files[]" multiple>
                            @error('files.*')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    @if($canManage)
                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label for="internal" class="small">Sichtbarkeit</label>
                                <select class="form-control form-control-sm" id="internal" name="internal">
                                    <option value="0">Öffentlich (Ersteller wird benachrichtigt)</option>
                                    <option value="1" @selected(old('internal'))>Intern (nur Bearbeiter)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label for="waiting_until" class="small">Auf Rückmeldung warten bis</label>
                                <input type="date" class="form-control form-control-sm" id="waiting_until" name="waiting_until"
                                       min="{{ now()->format('Y-m-d') }}" value="{{ old('waiting_until') }}">
                                @error('waiting_until')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    @endif
                </div>
                <button type="submit" class="btn btn-primary">Kommentar hinzufügen</button>
            </form>
        </div>
    @endcan
</div>

<div class="card">
    <div class="card-header bg-gradient-directional-grey-blue text-white">
        <h6 class="mb-0">Verlauf <span class="badge badge-light">{{ $show_ticket->comments->count() }}</span></h6>
    </div>
    <div class="card-body">
        @forelse($show_ticket->comments as $comment)
            <div class="card mb-2 @if($comment->internal) border border-warning @endif @if($comment->isSystem()) bg-light @endif">
                <div class="card-header py-1 small d-flex justify-content-between">
                    <span>
                        @if($comment->user && $comment->user->getMedia('profile')->isNotEmpty())
                            <img src="{{ $comment->user->photo() }}" class="avatar-xs rounded-circle" style="max-height: 24px; max-width: 24px;" alt="">
                        @endif
                        <strong>{{ $comment->author_name }}</strong>
                        @if($comment->user_id && $comment->user_id == $show_ticket->user_id)
                            <span class="badge badge-light border">Ersteller</span>
                        @endif
                        @if($comment->internal)
                            <span class="badge badge-warning">Intern</span>
                        @endif
                    </span>
                    <span class="text-muted" title="{{ $comment->created_at->format('d.m.Y H:i') }}">
                        {{ $comment->created_at->format('d.m.Y H:i') }}
                    </span>
                </div>
                <div class="card-body py-2">
                    {!! $comment->comment_html !!}
                    @if($comment->media->isNotEmpty())
                        <ul class="list-unstyled mb-0 mt-2 small">
                            @foreach($comment->media as $file)
                                <li>
                                    <a href="{{ route('tickets.files', [$show_ticket, $file]) }}" target="_blank" rel="noopener">
                                        <i class="fa fa-paperclip"></i> {{ $file->file_name }}
                                    </a>
                                    <span class="text-muted">({{ $file->human_readable_size }})</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-muted mb-0">Das Ticket wurde noch nicht bearbeitet.</p>
        @endforelse
    </div>
</div>
