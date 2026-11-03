{{--
    Formularfelder für gruppenunabhängige Meetings (Anlegen & Bearbeiten).
    Erwartet: $meeting (nullable), $options, $selection, $bookableRooms, $canBookRooms,
              beim Anlegen zusätzlich $meetingGroups und $canCreateFree.
--}}
@php
    $meeting   = $meeting ?? null;
    $isCreate  = $meeting === null;
    $prefix    = $prefix ?? ($isCreate ? 'hub_create' : 'hub_edit_' . $meeting->id);
    $defaultGroup = $isCreate
        ? old('group_id', ($canCreateFree ?? false) ? '' : ($meetingGroups->first()->id ?? ''))
        : '';
@endphp

<div class="space-y-4" x-data="{ groupId: @js((string) $defaultGroup) }">
    @if($errors->any())
        <div class="mtg-alert mtg-alert-warning mb-0">{{ $errors->first() }}</div>
    @endif

    <div>
        <label for="{{ $prefix }}_title" class="mtg-label">Titel <span class="mtg-required">*</span></label>
        <input type="text" class="mtg-input" name="title" id="{{ $prefix }}_title" required maxlength="255"
               placeholder="z. B. Projektrunde Schulfest" value="{{ old('title', $meeting?->title) }}">
    </div>

    @if($isCreate)
        <div>
            <label for="{{ $prefix }}_group" class="mtg-label">Kontext</label>
            <select name="group_id" id="{{ $prefix }}_group" class="mtg-select" x-model="groupId">
                @if($canCreateFree)
                    <option value="">Freies Meeting (ohne feste Gruppe)</option>
                @endif
                @foreach($meetingGroups as $g)
                    <option value="{{ $g->id }}">Gruppe: {{ $g->name }}</option>
                @endforeach
            </select>
            <p class="mtg-hint" x-show="groupId === ''">
                <i class="fas fa-info-circle"></i> Sichtbar nur für eingeladene Personen, Gruppen und Rollen.
            </p>
            <p class="mtg-hint" x-show="groupId !== ''" style="display:none;">
                <i class="fas fa-info-circle"></i> Alle Mitglieder der Gruppe nehmen automatisch teil – zusätzliche Personen kannst du unten einladen.
            </p>
        </div>
    @else
        <div class="flex items-center gap-2 text-sm text-gray-600">
            <span class="mtg-badge {{ $meeting->isFree() ? 'mtg-badge-free' : 'mtg-badge-group' }}">
                <i class="fas {{ $meeting->isFree() ? 'fa-globe' : 'fa-users' }}"></i> {{ $meeting->contextLabel() }}
            </span>
            <span class="text-xs text-gray-400">Der Kontext kann nachträglich nicht geändert werden.</span>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label for="{{ $prefix }}_date" class="mtg-label">Datum <span class="mtg-required">*</span></label>
            <input type="date" class="mtg-input" name="date" id="{{ $prefix }}_date" required
                   @if($isCreate) min="{{ now()->format('Y-m-d') }}" @endif
                   value="{{ old('date', $meeting?->date?->format('Y-m-d')) }}">
        </div>
        <div>
            <label for="{{ $prefix }}_start" class="mtg-label">Beginn <span class="mtg-required">*</span></label>
            <input type="time" class="mtg-input" name="start_time" id="{{ $prefix }}_start" required
                   value="{{ old('start_time', $meeting?->start_time) }}">
        </div>
        <div>
            <label for="{{ $prefix }}_end" class="mtg-label">Ende <span class="mtg-required">*</span></label>
            <input type="time" class="mtg-input" name="end_time" id="{{ $prefix }}_end" required
                   value="{{ old('end_time', $meeting?->end_time) }}">
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label for="{{ $prefix }}_location" class="mtg-label">Ort</label>
            <input type="text" class="mtg-input" name="location" id="{{ $prefix }}_location" maxlength="255"
                   placeholder="z. B. Lehrerzimmer" value="{{ old('location', $meeting?->location) }}">
        </div>
        <div>
            <label for="{{ $prefix }}_url" class="mtg-label">Video-Link</label>
            <input type="url" class="mtg-input" name="meeting_url" id="{{ $prefix }}_url" maxlength="500"
                   placeholder="https://…" value="{{ old('meeting_url', $meeting?->meeting_url) }}">
        </div>
    </div>

    <div>
        <label for="{{ $prefix }}_description" class="mtg-label">Beschreibung / Anlass</label>
        <textarea class="mtg-textarea" name="description" id="{{ $prefix }}_description" rows="2" maxlength="5000"
                  placeholder="Worum geht es? (optional)">{{ old('description', $meeting?->description) }}</textarea>
    </div>

    <div class="pt-2 border-t border-gray-100">
        @include('meetings.partials.participant_picker', [
            'options'   => $options,
            'selection' => old('users') !== null || old('groups') !== null || old('roles') !== null
                ? ['users' => old('users', []), 'organizers' => old('organizers', []), 'groups' => old('groups', []), 'roles' => old('roles', [])]
                : $selection,
        ])
    </div>

    @include('meetings.partials.room_booking_fields', [
        'prefix'          => $prefix,
        'bookRoomEnabled' => old('book_room', $meeting?->roomBooking ? 1 : 0),
        'selectedRoomId'  => old('room_id', $meeting?->roomBooking?->room_id),
        'bookableRooms'   => $bookableRooms,
        'canBookRooms'    => $canBookRooms,
    ])
</div>
