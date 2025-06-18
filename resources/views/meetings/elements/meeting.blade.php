<div class="card mb-3 border-secondary">
    <div class="card-header @if($meeting->date->isSameDay(now())) bg-gradient-directional-info @else bg-secondary @endif text-white @if($meeting->cancelled) bg-gradient-directional-danger @endif">
        <div class="row">
            <div class="col-md-8">
                <h5 class="card-title">{{ $meeting->title }}  @if($meeting->cancelled) (abgesagt) @endif</h5>
            </div>
            <div class="col-md-4 text-right">
                <a href="{{ route('meetings.edit', ['group' => $group->name, 'meeting' => $meeting->id]) }}" class="btn btn-sm btn-warning">Bearbeiten</a>
                @if(empty($meeting->cancelled) || !$meeting->cancelled)
                    <form action="{{ route('meetings.cancel', ['group' => $group->name, 'meeting' => $meeting->id]) }}" method="POST" style="display:inline-block">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Meeting wirklich absagen?')">Absagen</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body">
        <p class="card-text">
            <strong>Zeitraum:</strong> {{ $meeting->date->format('d.m.Y') }} <br>
            <strong>Uhrzeit:</strong> {{ $meeting->start_time }} - {{ $meeting->end_time }}
            @php
                $meetingStart = \Carbon\Carbon::parse($meeting->start_time);
                $meetingEnd = \Carbon\Carbon::parse($meeting->end_time);
                $meetingDuration = $meetingStart->diffInMinutes($meetingEnd);
                $themesDuration = $meeting->themes->sum('duration');
            @endphp
            @if($themesDuration > $meetingDuration)
                <br>
                <span class="text-danger"><strong>Hinweis:</strong> Die Summe der Themendauer ({{ $themesDuration }} min) überschreitet die Meetingdauer ({{ $meetingDuration }} min)!</span>
            @endif
            <br>
            @if($group->meeting_url)
                <strong>URL:</strong> <a href="{{ $group->meeting_url }}" target="_blank">{{ $group->meeting_url }}</a>
            @endif
        </p>
        @if($meeting->date->isSameDay(\Carbon\Carbon::now()))
            <a href="{{url(request()->segment(1).'/presence/'.$meeting->date->format('Ymd'))}}" class="btn btn-sm btn-primary mt-2">
                <i class="far fa-edit"></i> Anwesenheit
            </a>
        @endif
        @if($meeting->themes->count() > 0)
            <button class="btn btn-sm btn-info mt-2" type="button" data-toggle="collapse" data-target="#themes-{{ $meeting->id }}" aria-expanded="false" aria-controls="themes-{{ $meeting->id }}">
                {{$meeting->themes->count()}} Themen anzeigen
            </button>
            <div @if(!$meeting->date->isSameDay(\Carbon\Carbon::now())) class="collapse" @endif id="themes-{{ $meeting->id }}">
                <strong>Themenübersicht:</strong>
                <ul class="list-group">
                    @foreach($meeting->themes->sortByDesc('priority', ) as $theme)
                        @include('meetings.elements.theme', ['theme' => $theme])
                    @endforeach
                </ul>
            </div>
        @else
            <p class="text-muted">Keine Themen für dieses Meeting festgelegt.</p>
        @endif

        <!-- Button zum Öffnen des Modals für Themen -->
        <button class="btn btn-sm btn-success mt-2" data-toggle="modal" data-target="#addThemeModal-{{ $meeting->id }}">
            Thema hinzufügen/zuweisen
        </button>
        <!-- Modal für Themen anlegen/zuweisen -->
        <div class="modal fade" id="addThemeModal-{{ $meeting->id }}" tabindex="-1" role="dialog" aria-labelledby="addThemeModalLabel-{{ $meeting->id }}" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addThemeModalLabel-{{ $meeting->id }}">Thema anlegen oder zuweisen</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form action="{{ route('meetings.themes.store', [
                                                        'group' => $group->name,
                                                        'meeting' => $meeting->id,
                                                    ]) }}" method="POST">
                            @csrf
                            <h6>
                                Neues Thema anlegen
                            </h6>
                            <div class="form-group">
                                <div class="form-row">
                                    <label for="theme_title_{{ $meeting->id }}">Neues Thema anlegen</label><div class="text-danger">*</div>
                                    <input type="text" class="form-control" name="theme" id="theme_title_{{ $meeting->id }}" placeholder="Titel des Themas">
                                </div>
                                <div class="form-row">
                                    <label for="theme_duration_{{ $meeting->id }}">Dauer</label><div class="text-danger">*</div>
                                    <input type="number" class="form-control" name="duration" id="theme_duration_{{ $meeting->id }}" min="5" max="120" step="5" placeholder="Dauer in Minuten">
                                </div>
                                <div class="form-row">
                                    <label for="type">Typ</label><div class="text-danger">*</div>
                                    <select name="type" id="type" class="custom-select" required>
                                        <option disabled></option>
                                        @foreach($types as $type)
                                            <option value="{{$type->id}}" @if (old('type') == $type->id) selected @endif>{{$type->type}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-row">
                                    <label for="theme_goal_{{ $meeting->id }}">Ziel</label><div class="text-danger">*</div>
                                    <input type="text" class="form-control" name="goal" id="theme_goal_{{ $meeting->id }}" placeholder="Ziel">
                                </div>
                            </div>
                            <hr>
                            <h6>
                                Vorhandenes Thema zuweisen
                            </h6>
                            <div class="form-group">
                                <label for="existing_theme_{{ $meeting->id }}">Vorhandenes, offenes Thema zuweisen</label>
                                <select class="form-control" name="existing_theme_id" id="existing_theme_{{ $meeting->id }}">
                                    <option value="">-- Bitte wählen --</option>
                                    @foreach($openThemes as $openTheme)
                                        <option value="{{ $openTheme->id }}">{{ $openTheme->theme }} @if($openTheme->memory) (Themenspeicher) @endif
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Speichern</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Button zum Öffnen des Modals für Einladungsversand -->
        <button class="btn btn-primary btn-sm mt-2 pull-right" data-toggle="modal" data-target="#inviteModal-{{ $meeting->id }}">
            Einladung versenden
        </button>
        <!-- Modal für Einladungsversand -->
        <div class="modal fade" id="inviteModal-{{ $meeting->id }}" tabindex="-1" role="dialog" aria-labelledby="inviteModalLabel-{{ $meeting->id }}" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="inviteModalLabel-{{ $meeting->id }}">Einladung versenden</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form action="{{ route('meetings.invite', ['group' => $group->name, 'meeting' => $meeting->id]) }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="invitation_message_{{ $meeting->id }}">Zusätzliche Nachricht (optional):</label>
                                <textarea name="message" id="invitation_message_{{ $meeting->id }}" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="btn btn-primary">Einladung versenden</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- /Modal für Einladungsversand -->
        <!-- Historie Einladungsversand -->
        @if($meeting->invitation_sent_at && $meeting->invitation_sent_by)
            <div class="alert alert-info mt-2">
                Einladung versendet am {{ $meeting->invitation_sent_at->format('d.m.Y H:i') }} von {{ $meeting->invitationSender->name }}
            </div>
        @endif
    </div>
</div>
