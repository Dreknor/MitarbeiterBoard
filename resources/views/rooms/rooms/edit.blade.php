@extends('layouts.app')

@push('css')
    @vite(['resources/css/rooms.css'])
@endpush

@section('content')
<div class="rooms-wrapper">
<div class="max-w-2xl mx-auto px-3 sm:px-4 md:px-6 py-4 md:py-6">

    {{-- Zurück --}}
    <a href="{{ url('rooms/rooms') }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 shadow-sm mb-5">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Alle Räume
    </a>

    {{-- Raum bearbeiten --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-5">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <h1 class="font-semibold text-gray-800">Raum bearbeiten</h1>
        </div>
        <div class="px-5 py-5">
            <form action="{{ url('rooms/rooms/'.$room->id) }}" method="post">
                @csrf
                @method('put')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                        <input class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               type="text" name="name" value="{{ $room->name }}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Raumnummer</label>
                        <input class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               type="text" name="room_number" value="{{ $room->room_number }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Indiware Kürzel</label>
                        <input class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               type="text" name="indiware_shortname" value="{{ $room->indiware_shortname }}" maxlength="10" placeholder="z.B. K01">
                    </div>
                </div>
                <div class="flex items-center gap-2 mb-5">
                    <input class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                           type="checkbox" name="bookable" id="bookable" value="1" {{ $room->bookable ? 'checked' : '' }}>
                    <label class="text-sm text-gray-700 cursor-pointer" for="bookable">
                        Raum buchbar (aktiv – kann gebucht werden)
                    </label>
                </div>
                <button class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm" type="submit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Raum speichern
                </button>
            </form>
        </div>
    </div>

    {{-- Kalender-Feed --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <h2 class="font-semibold text-gray-800">Kalender-Feed</h2>
        </div>
        <div class="px-5 py-5 space-y-4">
            @if($room->feed_token)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Feed-URL (öffentlich, token-geschützt)</label>
                    <div class="flex gap-2">
                        <input id="feedUrl" type="text"
                               class="flex-1 rounded-xl border border-gray-300 px-3 py-2 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50"
                               readonly value="{{ $room->feed_url }}">
                        <button id="copyFeedUrl" type="button"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 bg-white hover:bg-gray-50 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            Kopieren
                        </button>
                    </div>
                    @if($room->feed_expires_at)
                        <p class="text-xs text-gray-400 mt-1">Gültig bis: {{ $room->feed_expires_at->format('d.m.Y H:i') }}</p>
                    @else
                        <p class="text-xs text-gray-400 mt-1">Kein Ablaufdatum gesetzt.</p>
                    @endif
                </div>

                <form action="{{ url('rooms/rooms/'.$room->id.'/feed/revoke') }}" method="post">
                    @csrf
                    <button class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium rounded-xl border border-red-200" type="submit">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        Feed widerrufen
                    </button>
                </form>
            @else
                <p class="text-sm text-gray-500">Aktuell ist kein Feed-Token erstellt.</p>
            @endif

            <div class="pt-4 border-t border-gray-100">
                <form action="{{ url('rooms/rooms/'.$room->id.'/feed/generate') }}" method="post">
                    @csrf
                    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
                        <div>
                            <label for="expires_in_days" class="block text-sm font-medium text-gray-700 mb-1">
                                Ablauf in Tagen <span class="text-gray-400 font-normal">(optional)</span>
                            </label>
                            <input type="number" min="1" name="expires_in_days" id="expires_in_days"
                                   class="w-full sm:w-32 rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="z.B. 90">
                        </div>
                        <button class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm" type="submit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Feed generieren / erneuern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('copyFeedUrl');
        if (!btn) return;
        btn.addEventListener('click', function () {
            const input = document.getElementById('feedUrl');
            if (!input) return;
            input.select();
            input.setSelectionRange(0, 99999);
            try {
                navigator.clipboard.writeText(input.value).then(() => {
                    btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Kopiert!';
                    setTimeout(() => {
                        btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> Kopieren';
                    }, 2000);
                });
            } catch (e) {
                document.execCommand('copy');
            }
        });
    });
</script>
@endsection
