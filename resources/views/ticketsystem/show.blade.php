{{-- Ticket-Detail (eingebunden in index und archiv, innerhalb von .ticket-wrapper) --}}
@php
    $canManage = auth()->user()->can('manage', $show_ticket);
    $canComment = auth()->user()->can('comment', $show_ticket);
    $isPinned = auth()->user()->pinned_tickets->contains($show_ticket->id);
    $heroClass = $show_ticket->isClosed() ? 'is-closed' : 'prio-'.$show_ticket->priority;
    $replies = $show_ticket->comments->reject->isSystem()->count();
@endphp

<div class="flex flex-col gap-5" x-data="{ closeOpen: false, replyVisible: false }" @keydown.escape.window="closeOpen = false">

    {{-- ── Kopf ──────────────────────────────────────────────── --}}
    <section class="tkt-hero {{ $heroClass }}">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-wrap items-center gap-1.5 min-w-0">
                <span class="tkt-badge tkt-badge-outline">#{{ $show_ticket->id }}</span>
                @include('ticketsystem.partials.badges', ['ticket' => $show_ticket])
            </div>
            <div class="flex items-center gap-1.5 ml-auto">
                <form action="{{ route('tickets.pin', $show_ticket) }}" method="post">
                    @csrf
                    <button type="submit" class="tkt-btn-icon {{ $isPinned ? 'is-active' : '' }}"
                            title="{{ $isPinned ? 'Nicht mehr anpinnen' : 'Anpinnen' }}" aria-label="{{ $isPinned ? 'Nicht mehr anpinnen' : 'Anpinnen' }}">
                        <i class="fas fa-thumbtack"></i>
                    </button>
                </form>
                @can('reopen', $show_ticket)
                    <form action="{{ route('tickets.reopen', $show_ticket) }}" method="post">
                        @csrf
                        <button type="submit" class="tkt-btn tkt-btn-sm tkt-btn-warning">
                            <i class="fas fa-undo"></i> Wieder öffnen
                        </button>
                    </form>
                @endcan
                @can('close', $show_ticket)
                    <button type="button" class="tkt-btn tkt-btn-sm tkt-btn-success" @click="closeOpen = true">
                        <i class="fas fa-check"></i> Schließen
                    </button>
                @endcan
            </div>
        </div>

        <h2 class="tkt-hero-title mt-3">{{ $show_ticket->title }}</h2>

        <dl class="tkt-facts mt-4 pt-4 border-t border-gray-100">
            <div class="min-w-0">
                <dt class="tkt-fact-label">Erstellt von</dt>
                <dd class="tkt-fact-value">
                    @include('ticketsystem.partials.avatar', ['user' => $show_ticket->user, 'small' => true])
                    <span class="min-w-0 leading-tight">{{ $show_ticket->user?->name ?? 'unbekannt' }}</span>
                </dd>
            </div>
            <div class="min-w-0">
                <dt class="tkt-fact-label">Zuständig</dt>
                <dd class="tkt-fact-value">
                    @if($show_ticket->assigned)
                        @include('ticketsystem.partials.avatar', ['user' => $show_ticket->assigned, 'small' => true])
                        <span class="min-w-0 leading-tight">{{ $show_ticket->assigned->name }}</span>
                    @else
                        <span class="text-amber-600"><i class="fas fa-user-slash mr-1"></i>niemand</span>
                    @endif
                </dd>
            </div>
            <div class="min-w-0">
                <dt class="tkt-fact-label">Erstellt</dt>
                <dd class="tkt-fact-value" title="{{ $show_ticket->created_at->format('d.m.Y H:i') }}">
                    {{ $show_ticket->created_at->format('d.m.Y') }}
                    <span class="text-gray-400 font-normal">{{ $show_ticket->created_at->format('H:i') }}</span>
                </dd>
            </div>
            <div class="min-w-0">
                @if($show_ticket->isClosed())
                    <dt class="tkt-fact-label">Geschlossen</dt>
                    <dd class="tkt-fact-value">
                        {{ ($show_ticket->closed_at ?? $show_ticket->updated_at)->format('d.m.Y') }}
                        <span class="text-gray-400 font-normal truncate">{{ $show_ticket->closedBy?->name ?? 'automatisch' }}</span>
                    </dd>
                @elseif($show_ticket->isWaiting() && $show_ticket->waiting_until)
                    <dt class="tkt-fact-label">Wartet bis</dt>
                    <dd class="tkt-fact-value {{ $show_ticket->isWaitingOverdue() ? 'text-red-600' : '' }}">
                        <i class="fas fa-hourglass-half text-xs"></i> {{ $show_ticket->waiting_until->format('d.m.Y') }}
                    </dd>
                @else
                    <dt class="tkt-fact-label">Letzte Aktivität</dt>
                    <dd class="tkt-fact-value">{{ ($show_ticket->comments->max('created_at') ?? $show_ticket->updated_at)?->diffForHumans() }}</dd>
                @endif
            </div>
        </dl>
    </section>

    {{-- ── Bearbeitung (nur Bearbeiter, offene Tickets) ─────────── --}}
    @if($canManage && !$show_ticket->isClosed())
        <section class="tkt-card">
            <div class="grid gap-5 p-4 sm:p-5 xl:grid-cols-2">
                <form action="{{ route('tickets.assign', $show_ticket) }}" method="post" data-ticket-form>
                    @csrf
                    <label for="assign_user" class="tkt-label"><i class="fas fa-user-check text-gray-400 mr-1"></i> Zuständig</label>
                    <div class="flex gap-2">
                        <select name="user_id" id="assign_user" class="tkt-select flex-1 min-w-0">
                            <option value="">– niemand –</option>
                            @foreach($assignable as $user)
                                <option value="{{ $user->id }}" @selected($show_ticket->assigned_to == $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="tkt-btn tkt-btn-secondary" title="Zuweisung speichern">
                            <i class="fas fa-check"></i><span class="hidden sm:inline">Zuweisen</span>
                        </button>
                    </div>
                    @if($show_ticket->assigned_to != auth()->id())
                        <button type="submit" name="user_id" value="{{ auth()->id() }}"
                                class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-800">
                            <i class="fas fa-hand-paper"></i> Ich übernehme das Ticket
                        </button>
                    @endif
                </form>

                <form action="{{ route('tickets.update', $show_ticket) }}" method="post" data-ticket-form>
                    @csrf
                    @method('PATCH')
                    <span class="tkt-label"><i class="fas fa-tag text-gray-400 mr-1"></i> Kategorie &amp; Priorität</span>
                    <div class="flex flex-col gap-2">
                        <select name="category_id" class="tkt-select w-full" aria-label="Kategorie">
                            <option value="">– keine Kategorie –</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected($show_ticket->category_id == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <div class="flex gap-2">
                            <select name="priority" class="tkt-select flex-1 min-w-0" aria-label="Priorität">
                                @foreach(\App\Models\Ticket::PRIORITY_LABELS as $value => $label)
                                    <option value="{{ $value }}" @selected($show_ticket->priority == $value)>{{ ucfirst($label) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="tkt-btn tkt-btn-secondary" title="Speichern">
                                <i class="fas fa-save"></i><span class="hidden sm:inline">Speichern</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    @endif

    {{-- ── Beschreibung ─────────────────────────────────────────── --}}
    <section class="tkt-card">
        <div class="tkt-card-head">
            <h3 class="tkt-card-title"><i class="fas fa-align-left"></i> Beschreibung</h3>
        </div>
        <div class="tkt-card-body">
            <div class="tkt-prose">{!! $show_ticket->description_html !!}</div>
            @include('ticketsystem.partials.attachments', [
                'files' => $show_ticket->getMedia('ticket_files'),
                'ticket' => $show_ticket,
                'class' => 'mt-4 pt-4 border-t border-gray-100',
            ])
        </div>
    </section>

    {{-- ── Verlauf ─────────────────────────────────────────────── --}}
    <section>
        <div class="flex items-center justify-between gap-3 mb-3 px-1">
            <h3 class="tkt-section-title">Verlauf</h3>
            <span class="text-xs text-gray-500">{{ $replies }} {{ $replies === 1 ? 'Antwort' : 'Antworten' }}</span>
        </div>

        <div class="tkt-thread">
            @forelse($show_ticket->comments as $comment)
                @if($comment->isSystem())
                    <div class="tkt-event">
                        <span class="tkt-event-icon"><i class="fas fa-info"></i></span>
                        <p class="tkt-event-text min-w-0">
                            @if($comment->user)<strong>{{ $comment->user->name }}</strong> · @endif
                            {!! $comment->comment_html !!}
                            <span class="text-gray-400 whitespace-nowrap" title="{{ $comment->created_at->format('d.m.Y H:i') }}">· {{ $comment->created_at->format('d.m.Y H:i') }}</span>
                        </p>
                    </div>
                @else
                    @php $fromOwner = $comment->user_id && $comment->user_id == $show_ticket->user_id; @endphp
                    <article class="tkt-msg {{ $comment->internal ? 'is-internal' : '' }} {{ $fromOwner ? 'is-owner' : '' }}">
                        <span class="hidden sm:flex">
                            @include('ticketsystem.partials.avatar', ['user' => $comment->user])
                        </span>
                        <div class="tkt-msg-bubble">
                            <header class="tkt-msg-head">
                                <span class="sm:hidden">@include('ticketsystem.partials.avatar', ['user' => $comment->user, 'small' => true])</span>
                                <strong class="text-gray-900">{{ $comment->author_name }}</strong>
                                @if($fromOwner)
                                    <span class="tkt-badge tkt-badge-blue">Ersteller</span>
                                @endif
                                @if($comment->internal)
                                    <span class="tkt-badge tkt-badge-amber"><i class="fas fa-lock text-[10px]"></i> intern</span>
                                @endif
                                <time class="ml-auto text-xs text-gray-400" datetime="{{ $comment->created_at->toIso8601String() }}"
                                      title="{{ $comment->created_at->format('d.m.Y H:i') }}">
                                    {{ $comment->created_at->format('d.m.Y H:i') }}
                                </time>
                            </header>
                            <div class="tkt-msg-body">
                                <div class="tkt-prose">{!! $comment->comment_html !!}</div>
                                @include('ticketsystem.partials.attachments', [
                                    'files' => $comment->media,
                                    'ticket' => $show_ticket,
                                    'class' => 'mt-3',
                                ])
                            </div>
                        </div>
                    </article>
                @endif
            @empty
                <div class="tkt-card">
                    <div class="tkt-empty">
                        <span class="tkt-empty-icon"><i class="far fa-comments"></i></span>
                        <p>Noch keine Antworten.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </section>

    {{-- ── Antworten ─────────────────────────────────────────────── --}}
    @if($canComment)
        <section class="tkt-card scroll-mt-4" id="antworten" x-data="{ internal: '{{ old('internal') ? '1' : '0' }}' }"
                 x-intersect:enter="replyVisible = true" x-intersect:leave="replyVisible = false">
            <div class="tkt-card-head">
                <h3 class="tkt-card-title"><i class="fas fa-reply"></i> Antworten</h3>
            </div>
            <form action="{{ route('tickets.comments.store', $show_ticket) }}" method="post" enctype="multipart/form-data"
                  class="tkt-card-body flex flex-col gap-4" data-ticket-form>
                @csrf

                @if($canManage)
                    <div class="tkt-segment" role="radiogroup" aria-label="Sichtbarkeit">
                        <input type="radio" name="internal" id="vis_public" value="0" x-model="internal" @checked(!old('internal'))>
                        <label for="vis_public"><i class="fas fa-globe-europe"></i> Öffentlich</label>
                        <input type="radio" name="internal" id="vis_internal" value="1" x-model="internal" @checked(old('internal'))>
                        <label for="vis_internal" class="is-internal"><i class="fas fa-lock"></i> Interne Notiz</label>
                    </div>
                    <p class="tkt-hint -mt-2" x-show="internal !== '1'">Der Ersteller wird per Mail benachrichtigt.</p>
                    <p class="tkt-hint -mt-2 text-amber-700" x-show="internal === '1'" x-cloak>Nur für Bearbeiter sichtbar – der Ersteller erfährt nichts davon.</p>
                @endif

                <div>
                    <label for="comment" class="sr-only">Nachricht</label>
                    <textarea class="tkt-textarea ticket-editor" id="comment" name="comment">{{ old('comment') }}</textarea>
                    <p class="tkt-error hidden" data-editor-error>Bitte eine Nachricht eingeben.</p>
                    @error('comment')<p class="tkt-error">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-4 {{ $canManage ? 'md:grid-cols-2' : '' }}">
                    <div>
                        <span class="tkt-label">Anhänge</span>
                        @include('ticketsystem.partials.file-picker', ['compact' => true])
                    </div>
                    @if($canManage)
                        <div x-show="internal !== '1'">
                            <label for="waiting_until" class="tkt-label">Auf Rückmeldung warten bis <span class="font-normal text-gray-400">(optional)</span></label>
                            <input type="date" class="tkt-input" id="waiting_until" name="waiting_until"
                                   min="{{ now()->format('Y-m-d') }}" value="{{ old('waiting_until') }}">
                            <p class="tkt-hint">Setzt das Ticket auf „wartend“. Ohne Antwort wird es danach automatisch geschlossen.</p>
                            @error('waiting_until')<p class="tkt-error">{{ $message }}</p>@enderror
                        </div>
                    @endif
                </div>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
                    <button type="submit" class="tkt-btn tkt-btn-primary w-full sm:w-auto">
                        <i class="fas fa-paper-plane"></i>
                        <span data-loading-label="Wird gesendet …" x-text="internal === '1' ? 'Notiz speichern' : 'Antwort senden'">Antwort senden</span>
                    </button>
                </div>
            </form>
        </section>

        {{-- Schnellzugriff auf Mobilgeräten --}}
        <a href="#antworten" class="lg:hidden fixed bottom-5 right-5 z-30 tkt-btn tkt-btn-primary rounded-full shadow-lg" style="padding-inline: 1.1rem"
           x-show.important="!replyVisible" x-transition.opacity>
            <i class="fas fa-reply"></i> Antworten
        </a>
    @elseif($show_ticket->isClosed())
        <section class="tkt-card">
            <div class="tkt-empty">
                <span class="tkt-empty-icon"><i class="fas fa-lock"></i></span>
                <p class="font-medium text-gray-700">Dieses Ticket ist geschlossen.</p>
                @can('reopen', $show_ticket)
                    <p>Besteht das Problem weiterhin, kannst du es wieder öffnen.</p>
                    <form action="{{ route('tickets.reopen', $show_ticket) }}" method="post" class="mt-2">
                        @csrf
                        <button type="submit" class="tkt-btn tkt-btn-secondary"><i class="fas fa-undo"></i> Wieder öffnen</button>
                    </form>
                @endcan
            </div>
        </section>
    @endif

    {{-- ── Schließen-Dialog ─────────────────────────────────────── --}}
    @can('close', $show_ticket)
        <template x-teleport="body">
            <div class="ticket-wrapper" style="padding: 0">
                <div class="tkt-modal-backdrop" x-show="closeOpen" x-transition.opacity x-cloak @click.self="closeOpen = false">
                    <form action="{{ route('tickets.close', $show_ticket) }}" method="post" class="tkt-modal" role="dialog" aria-modal="true" aria-labelledby="closeTitle" data-ticket-form>
                        @csrf
                        <div class="tkt-modal-header">
                            <h3 class="tkt-modal-title" id="closeTitle">Ticket schließen</h3>
                            <button type="button" class="tkt-btn-icon" @click="closeOpen = false" aria-label="Abbrechen"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="tkt-modal-body">
                            <p class="text-sm text-gray-600 mb-4">
                                „{{ $show_ticket->title }}“ wird archiviert.
                                @if(auth()->id() != $show_ticket->user_id) Der Ersteller wird benachrichtigt. @endif
                            </p>
                            <label for="close_reason" class="tkt-label">Abschlussnotiz <span class="font-normal text-gray-400">(optional, für alle sichtbar)</span></label>
                            <textarea name="reason" id="close_reason" rows="3" maxlength="1000" class="tkt-textarea" style="min-height: 5rem"
                                      placeholder="z. B. Problem behoben, Gerät getauscht …"></textarea>
                        </div>
                        <div class="tkt-modal-footer">
                            <button type="button" class="tkt-btn tkt-btn-secondary" @click="closeOpen = false">Abbrechen</button>
                            <button type="submit" class="tkt-btn tkt-btn-success"><i class="fas fa-check"></i> <span data-loading-label="Wird geschlossen …">Ticket schließen</span></button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    @endcan
</div>
