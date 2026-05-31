@extends('layouts.app')

@push('css')
    @vite('resources/css/meetings.css')
@endpush

@section('content')
<div class="meeting-wrapper" x-data="{ showCreate: false }" x-cloak>

    {{-- Kopfbereich --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="mtg-page-title text-2xl font-bold text-gray-900">Meetings</h1>
            <p class="text-sm text-gray-500 mt-0.5">Gruppe: {{ $group->name }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('meetings.past', ['groupname' => $group->name]) }}" class="mtg-btn mtg-btn-secondary">
                <i class="fas fa-archive"></i>
                <span class="hidden sm:inline">Archiv</span>
            </a>
            <button type="button" class="mtg-btn mtg-btn-primary" @click="showCreate = true">
                <i class="fas fa-plus"></i>
                Meeting erstellen
            </button>
        </div>
    </div>

    {{-- Heutige Meetings --}}
    @if($meetingsToday->count())
        <div class="mb-6">
            <h2 class="mtg-section-title mb-3 flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full bg-blue-500"></span>
                Heute
            </h2>
            <div class="grid grid-cols-1 gap-4">
                @foreach($meetingsToday as $meeting)
                    @include('meetings.elements.meeting', ['meeting' => $meeting, 'group' => $group])
                @endforeach
            </div>
        </div>
    @endif

    {{-- Nächste Meetings --}}
    <div class="mb-6">
        <h2 class="mtg-section-title mb-3">Nächste Meetings</h2>
        @if($otherMeetings->count())
            <div class="grid grid-cols-1 gap-4">
                @foreach($otherMeetings as $meeting)
                    @include('meetings.elements.meeting', ['meeting' => $meeting, 'group' => $group])
                @endforeach
            </div>
        @else
            <div class="mtg-card p-8 text-center text-gray-500">
                <i class="far fa-calendar-plus text-3xl text-gray-300 mb-3 block"></i>
                Keine zukünftigen Meetings geplant.
                <div class="mt-4">
                    <button type="button" class="mtg-btn mtg-btn-primary" @click="showCreate = true">
                        <i class="fas fa-plus"></i> Erstes Meeting erstellen
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- Modal: Meeting erstellen --}}
    <div class="mtg-modal-backdrop" x-show="showCreate" x-transition.opacity
         @keydown.escape.window="showCreate = false" style="display:none;">
        <div class="mtg-modal" @click.outside="showCreate = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-3"
             x-transition:enter-end="opacity-100 translate-y-0">
            <div class="mtg-modal-header">
                <h3 class="mtg-modal-title">Neues Meeting erstellen</h3>
                <button type="button" class="mtg-modal-close" @click="showCreate = false" aria-label="Schließen">&times;</button>
            </div>
            <form action="{{ route('meetings.store', ['group' => $group->name]) }}" method="POST">
                @csrf
                <div class="mtg-modal-body space-y-4">
                    <div>
                        <label for="title" class="mtg-label">Titel des Meetings <span class="mtg-required">*</span></label>
                        <input type="text" class="mtg-input" name="title" id="title" required autofocus>
                    </div>
                    <div>
                        <label for="date" class="mtg-label">Datum <span class="mtg-required">*</span></label>
                        <input type="date" class="mtg-input" name="date" id="date" required min="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="start_time" class="mtg-label">Startzeit <span class="mtg-required">*</span></label>
                            <input type="time" class="mtg-input" name="start_time" id="start_time" required>
                        </div>
                        <div>
                            <label for="end_time" class="mtg-label">Endzeit <span class="mtg-required">*</span></label>
                            <input type="time" class="mtg-input" name="end_time" id="end_time" required>
                        </div>
                    </div>
                </div>
                <div class="mtg-modal-footer">
                    <button type="button" class="mtg-btn mtg-btn-secondary" @click="showCreate = false">Abbrechen</button>
                    <button type="submit" class="mtg-btn mtg-btn-primary">Meeting erstellen</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection


@push('js')
    <script>
        // Prioritäts-Slider (AJAX) – bleibt erhalten
        $('.meeting-wrapper input[type=range]').on("change", function () {
            let theme = $(this).data('theme');
            $.ajax({
                type: "POST",
                url: '{{ url('priorities') }}',
                data: {
                    "priority": $(this).val(),
                    'theme': theme,
                    "_token": "{{ csrf_token() }}",
                },
                success: function (responseText) {
                    let percent = 100 - responseText['priority'];
                    let element = document.getElementById('priority_' + theme);
                    if (element) {
                        element.innerHTML = '<div class="mtg-progress"><span style="width:' + percent + '%"></span></div>';
                    }
                    if (typeof sortTable === 'function') {
                        sortTable(responseText['day'] + "_themes");
                    }
                }
            });
        });

    </script>
@endpush
