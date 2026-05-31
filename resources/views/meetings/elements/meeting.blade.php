@php
    $isToday      = $meeting->date->isSameDay(now());
    $isCancelled  = (bool) $meeting->cancelled;
    $bandClass    = $isCancelled ? 'mtg-band-cancelled' : ($isToday ? 'mtg-band-today' : 'mtg-band-upcoming');
    $meetingStart = \Carbon\Carbon::parse($meeting->start_time);
    $meetingEnd   = \Carbon\Carbon::parse($meeting->end_time);
    $meetingDur   = $meetingStart->diffInMinutes($meetingEnd);
    $themesDur    = $meeting->themes->sum('duration');
@endphp

<div class="mtg-card"
     x-data="{ showThemes: {{ $isToday ? 'true' : 'false' }}, showAddTheme: false, showInvite: false, showTasks: false }">

    {{-- Kopfband --}}
    <div class="mtg-band {{ $bandClass }}">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="text-lg font-bold truncate">{{ $meeting->title }}</h3>
                    @if($isCancelled)
                        <span class="mtg-badge bg-white/20 text-white">Abgesagt</span>
                    @elseif($isToday)
                        <span class="mtg-badge bg-white/20 text-white">Heute</span>
                    @endif
                </div>
                <div class="text-sm text-white/90 mt-1 flex flex-wrap items-center gap-x-4 gap-y-1">
                    <span><i class="far fa-calendar-alt mr-1"></i>{{ $meeting->date->format('d.m.Y') }}</span>
                    <span><i class="far fa-clock mr-1"></i>{{ $meeting->start_time }} – {{ $meeting->end_time }}</span>
                </div>
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
                <a href="{{ route('meetings.edit', ['group' => $group->name, 'meeting' => $meeting->id]) }}"
                   class="mtg-btn-icon bg-white/15 hover:bg-white/25 text-white" title="Bearbeiten">
                    <i class="fas fa-pen"></i>
                </a>
                @if(! $isCancelled)
                    <form action="{{ route('meetings.cancel', ['group' => $group->name, 'meeting' => $meeting->id]) }}" method="POST"
                          onsubmit="return confirm('Meeting wirklich absagen?');">
                        @csrf
                        <button type="submit" class="mtg-btn-icon bg-white/15 hover:bg-white/25 text-white" title="Absagen">
                            <i class="fas fa-ban"></i>
                        </button>
                    </form>
                @else
                    <form action="{{ route('meetings.reactivate', ['group' => $group->name, 'meeting' => $meeting->id]) }}" method="POST"
                          onsubmit="return confirm('Absage aufheben und Meeting wieder aktivieren?');">
                        @csrf
                        <button type="submit" class="mtg-btn-icon bg-white/15 hover:bg-white/25 text-white" title="Wieder aktivieren">
                            <i class="fas fa-undo"></i>
                        </button>
                    </form>
                @endif
                <form action="{{ route('meetings.destroy', ['group' => $group->name, 'meeting' => $meeting->id]) }}" method="POST"
                      onsubmit="return confirm('Meeting endgültig löschen? Die zugeordneten Themen bleiben erhalten.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="mtg-btn-icon bg-white/15 hover:bg-white/25 text-white" title="Löschen">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Körper --}}
    <div class="p-5">

        @if($themesDur > $meetingDur)
            <div class="mtg-alert mtg-alert-warning flex items-start gap-2">
                <i class="fas fa-exclamation-triangle mt-0.5"></i>
                <span>Die Summe der Themendauer (<strong>{{ $themesDur }} min</strong>) überschreitet die Meetingdauer (<strong>{{ $meetingDur }} min</strong>).</span>
            </div>
        @endif

        @if($group->meeting_url)
            <p class="text-sm text-gray-600 mb-3">
                <i class="fas fa-video mr-1 text-gray-400"></i>
                <a href="{{ $group->meeting_url }}" target="_blank" class="text-blue-600 hover:underline break-all">{{ $group->meeting_url }}</a>
            </p>
        @endif

        @if($meeting->meetingTasks->count())
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach($meeting->meetingTasks as $task)
                    <span class="mtg-badge mtg-badge-gray">
                        <i class="fas fa-user-tag"></i>
                        {{ $task->role }}: {{ $task->user?->name }}@if($task->notes) <span class="text-gray-400">({{ $task->notes }})</span>@endif
                    </span>
                @endforeach
            </div>
        @endif

        @if($meeting->themes->count() > 0)
            <button type="button" class="mtg-btn mtg-btn-secondary mtg-btn-sm mb-3" @click="showThemes = !showThemes">
                <i class="fas" :class="showThemes ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                <span x-text="showThemes ? 'Themen ausblenden' : '{{ $meeting->themes->count() }} Themen anzeigen'"></span>
            </button>
            <div x-show="showThemes" x-collapse>
                <ul class="space-y-2 mb-2">
                    @foreach($meeting->themes->sortByDesc('priority') as $theme)
                        @include('meetings.elements.theme', ['theme' => $theme, 'meeting' => $meeting, 'group' => $group])
                    @endforeach
                </ul>
            </div>
        @else
            <p class="text-sm text-gray-400 mb-3 italic">Keine Themen für dieses Meeting festgelegt.</p>
        @endif

        {{-- Aktionsleiste --}}
        <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-gray-100">
            @if($isToday)
                <a href="{{ url(request()->segment(1).'/presence/'.$meeting->date->format('Ymd')) }}" class="mtg-btn mtg-btn-primary mtg-btn-sm">
                    <i class="far fa-edit"></i> Anwesenheit
                </a>
            @endif
            <button type="button" class="mtg-btn mtg-btn-success mtg-btn-sm" @click="showAddTheme = true">
                <i class="fas fa-plus"></i> Thema
            </button>
            <button type="button" class="mtg-btn mtg-btn-secondary mtg-btn-sm" @click="showTasks = true">
                <i class="fas fa-user-tag"></i> Aufgaben &amp; Rollen
            </button>
            <form action="{{ route('meetings.assignThemes', ['group' => $group->name, 'meeting' => $meeting->id]) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="mtg-btn mtg-btn-secondary mtg-btn-sm">
                    <i class="fas fa-layer-group"></i> Tages-Themen zuweisen
                </button>
            </form>
            <button type="button" class="mtg-btn mtg-btn-secondary mtg-btn-sm ml-auto" @click="showInvite = true">
                <i class="far fa-paper-plane"></i> Einladung
            </button>
        </div>

        @if($meeting->invitation_sent_at && $meeting->invitation_sent_by)
            <div class="mtg-alert mtg-alert-info mt-4 mb-0 flex items-center gap-2">
                <i class="far fa-paper-plane"></i>
                Einladung versendet am {{ $meeting->invitation_sent_at->format('d.m.Y H:i') }} von {{ optional($meeting->invitationSender)->name }}
            </div>
        @endif
    </div>

    {{-- ============ Modals ============ --}}

    {{-- Modal: Thema anlegen/zuweisen --}}
    <div class="mtg-modal-backdrop" x-show="showAddTheme" x-transition.opacity
         @keydown.escape.window="showAddTheme = false" style="display:none;">
        <div class="mtg-modal mtg-modal-lg" @click.outside="showAddTheme = false">
            <div class="mtg-modal-header">
                <h3 class="mtg-modal-title">Thema anlegen oder zuweisen</h3>
                <button type="button" class="mtg-modal-close" @click="showAddTheme = false" aria-label="Schließen">&times;</button>
            </div>
            <form action="{{ route('meetings.themes.store', ['group' => $group->name, 'meeting' => $meeting->id]) }}" method="POST">
                @csrf
                <div class="mtg-modal-body space-y-5">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 mb-3">Neues Thema anlegen</h4>
                        <div class="space-y-3">
                            <div>
                                <label class="mtg-label">Titel des Themas <span class="mtg-required">*</span></label>
                                <input type="text" class="mtg-input" name="theme" placeholder="Titel des Themas">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="mtg-label">Dauer (Minuten) <span class="mtg-required">*</span></label>
                                    <input type="number" class="mtg-input" name="duration" min="5" max="240" step="5" placeholder="z. B. 15">
                                </div>
                                <div>
                                    <label class="mtg-label">Typ <span class="mtg-required">*</span></label>
                                    <select name="type" class="mtg-select">
                                        <option value="">-- bitte wählen --</option>
                                        @foreach($types as $type)
                                            <option value="{{ $type->id }}">{{ $type->type }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="mtg-label">Ziel <span class="mtg-required">*</span></label>
                                <input type="text" class="mtg-input" name="goal" placeholder="Ziel des Themas">
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="border-t border-gray-200"></div>
                        <span class="absolute left-1/2 -translate-x-1/2 -top-2.5 bg-white px-3 text-xs text-gray-400 uppercase tracking-wide">oder</span>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 mb-3">Vorhandenes Thema zuweisen</h4>
                        <select class="mtg-select" name="existing_theme_id">
                            <option value="">-- offenes Thema wählen --</option>
                            @foreach($openThemes as $openTheme)
                                <option value="{{ $openTheme->id }}">{{ $openTheme->theme }}@if($openTheme->memory) (Themenspeicher)@endif</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mtg-modal-footer">
                    <button type="button" class="mtg-btn mtg-btn-secondary" @click="showAddTheme = false">Abbrechen</button>
                    <button type="submit" class="mtg-btn mtg-btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Einladung versenden --}}
    <div class="mtg-modal-backdrop" x-show="showInvite" x-transition.opacity
         @keydown.escape.window="showInvite = false" style="display:none;">
        <div class="mtg-modal" @click.outside="showInvite = false">
            <div class="mtg-modal-header">
                <h3 class="mtg-modal-title">Einladung versenden</h3>
                <button type="button" class="mtg-modal-close" @click="showInvite = false" aria-label="Schließen">&times;</button>
            </div>
            <form action="{{ route('meetings.invite', ['group' => $group->name, 'meeting' => $meeting->id]) }}" method="POST">
                @csrf
                <div class="mtg-modal-body">
                    <p class="text-sm text-gray-500 mb-3">Die Einladung wird an alle Mitglieder der Gruppe <strong>{{ $group->name }}</strong> versendet.</p>
                    <label class="mtg-label">Zusätzliche Nachricht (optional)</label>
                    <textarea name="message" class="mtg-textarea" rows="3" placeholder="Optionale Nachricht …"></textarea>
                </div>
                <div class="mtg-modal-footer">
                    <button type="button" class="mtg-btn mtg-btn-secondary" @click="showInvite = false">Abbrechen</button>
                    <button type="submit" class="mtg-btn mtg-btn-primary"><i class="far fa-paper-plane"></i> Versenden</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Aufgaben & Rollen --}}
    @include('meetings.partials.tasks_modal', ['meeting' => $meeting, 'group' => $group])
</div>
