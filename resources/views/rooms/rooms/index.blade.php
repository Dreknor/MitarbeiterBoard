@extends('layouts.app')

@push('css')
    @vite(['resources/css/rooms.css'])
@endpush

@section('content')
<div class="rooms-wrapper">
<div class="max-w-5xl mx-auto px-3 sm:px-4 md:px-6 py-4 md:py-6">

    {{-- Flash-Fehler --}}
    @if(session('fehler') && count(session('fehler')) > 0)
        <div class="mb-4 rounded-xl bg-red-50 border border-red-200 p-4 flex gap-3">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <ul class="text-red-700 text-sm space-y-1">
                @foreach(session('fehler') as $f)
                    <li>{{ $f }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Flash-Meldung --}}
    @if(session('Meldung'))
        <div class="mb-4 rounded-xl bg-{{ session('type') === 'success' ? 'green' : 'yellow' }}-50 border border-{{ session('type') === 'success' ? 'green' : 'yellow' }}-200 p-4 text-{{ session('type') === 'success' ? 'green' : 'yellow' }}-700 text-sm">
            {{ session('Meldung') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                Raumplanung
            </h1>
            <p class="text-gray-500 text-sm mt-1">Übersicht aller verfügbaren Räume</p>
        </div>
        @can('manage rooms')
        <button onclick="document.getElementById('createRoomPanel').classList.toggle('hidden')"
                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Neuer Raum
        </button>
        @endcan
    </div>
    @can('manage rooms')
        {{-- Neuen Raum erstellen --}}
        <div id="createRoomPanel" class="hidden mb-6">
            <div class="bg-white rounded-2xl border border-blue-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Neuen Raum erstellen
                    </h2>
                    <button onclick="document.getElementById('createRoomPanel').classList.add('hidden')"
                            class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="px-5 py-4">
                    <form action="{{ url('rooms/rooms') }}" method="post">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       type="text" name="name" required placeholder="z.B. Konferenzraum 1">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Raumnummer</label>
                                <input class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       type="text" name="room_number" placeholder="z.B. A101">
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mb-5">
                            <input class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                   type="checkbox" name="bookable" id="bookable_create" value="1" checked>
                            <label class="text-sm text-gray-700 cursor-pointer" for="bookable_create">
                                Raum buchbar (aktiv)
                            </label>
                        </div>
                        <button class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm" type="submit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Raum erstellen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endcan
    {{-- Räume-Liste --}}
    @if($rooms->count() > 0)
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6 divide-y divide-gray-100">
            @foreach($rooms as $room)
                <div class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50">
                    {{-- Status-Dot --}}
                    <span class="shrink-0 w-2 h-2 rounded-full
                        {{ !$room->bookable ? 'bg-gray-300' : ($room->availability ? 'bg-green-400' : 'bg-red-400') }}">
                    </span>

                    {{-- Name & Nummer --}}
                    <div class="flex-1 min-w-0">
                        <span class="font-medium text-sm text-gray-800 truncate block">{{ $room->name }}</span>
                        @if($room->room_number)
                            <span class="text-xs text-gray-400">Nr. {{ $room->room_number }}</span>
                        @endif
                    </div>

                    {{-- Status-Badge --}}
                    <div class="shrink-0 hidden sm:block">
                        @if(!$room->bookable)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Inaktiv</span>
                        @elseif($room->availability)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Frei</span>
                        @else
                            <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700">Belegt</span>
                        @endif
                    </div>

                    {{-- Aktionen --}}
                    <div class="shrink-0 flex items-center gap-1">
                        <a href="{{ url('rooms/rooms/'.$room->id) }}"
                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <span class="hidden sm:inline">Details</span>
                        </a>
                        @can('manage rooms')
                            <a href="{{ url('rooms/rooms/'.$room->id.'/edit') }}"
                               class="inline-flex items-center p-1.5 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            @if($room->bookings->count() == 0)
                                <button type="submit" form="deleteForm_{{ $room->id }}" title="Raum löschen"
                                        class="inline-flex items-center p-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100"
                                        onclick="return confirm('Raum wirklich löschen?')">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                                <form method="post" id="deleteForm_{{ $room->id }}" action="{{ url('rooms/rooms/'.$room->id) }}" class="hidden">
                                    @csrf
                                    @method('delete')
                                </form>
                            @endif
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center mb-6">
            <svg class="w-14 h-14 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            <p class="text-gray-500 text-sm">Es wurden noch keine Räume angelegt.</p>
        </div>
    @endif


    @can('manage rooms')

        {{-- Import --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <button onclick="document.getElementById('importPanel').classList.toggle('hidden')"
                    class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-gray-50">
                <span class="font-semibold text-gray-700 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Plan aus Indiware importieren
                </span>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div id="importPanel" class="hidden border-t border-gray-100 px-5 py-4">
                @include('rooms.rooms.import')
            </div>
        </div>
    @endcan

</div>
</div>
@endsection

@push('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/piexif.min.js" type="text/javascript"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/sortable.min.js" type="text/javascript"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/purify.min.js" type="text/javascript"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/fileinput.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/themes/fas/theme.min.js"></script>
    <script>
        $("#file").fileinput({
            'showUpload': false,
            'previewFileType': 'any',
            maxFileSize: {{ config('app.maxFileSize') }},
            'theme': "fas",
        });
    </script>
@endpush

@push('css')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/css/fileinput.min.css" media="all" rel="stylesheet" type="text/css"/>
@endpush
