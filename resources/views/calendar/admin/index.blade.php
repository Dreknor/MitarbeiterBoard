@extends('layouts.app')

@push('css')
    @vite('resources/css/calendar.css')
@endpush

@section('content')
<div class="px-4 py-4">

    {{-- ─── Seiten-Header ─────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">⚙️ Kalender-Verwaltung</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                <a href="{{ route('calendar.index') }}" class="text-blue-600 hover:underline">← Zurück zur Kalender-Ansicht</a>
            </p>
        </div>

        <div class="flex items-center gap-3">
            {{-- Verbindungsstatus --}}
            @if($connectionStatus['success'])
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 border border-green-300">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                    CalDAV verbunden
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 border border-red-300"
                      title="{{ $connectionStatus['message'] }}">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    Verbindung fehlgeschlagen
                </span>
            @endif

            {{-- Manueller Sync --}}
            <form action="{{ route('calendar.admin.sync') }}" method="POST">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-blue-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Jetzt synchronisieren
                </button>
            </form>

            <a href="{{ route('calendar.admin.logs') }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-md transition-colors border border-gray-300">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Sync-Logs
            </a>
        </div>
    </div>

    {{-- ─── Flash-Meldungen ───────────────────────────────────────────── --}}
    @if(session('Meldung'))
        <div class="mb-4 px-4 py-3 rounded-md text-sm font-medium
            {{ session('type') === 'success' ? 'bg-green-50 border border-green-300 text-green-800' : '' }}
            {{ session('type') === 'warning' ? 'bg-amber-50 border border-amber-300 text-amber-800' : '' }}
            {{ session('type') === 'danger'  ? 'bg-red-50 border border-red-300 text-red-800' : '' }}">
            {{ session('Meldung') }}
        </div>
    @endif

    {{-- ─── Verbindungsdetails (bei Fehler) ──────────────────────────── --}}
    @if(!$connectionStatus['success'])
        <div class="mb-4 px-4 py-3 rounded-md bg-red-50 border border-red-200 text-red-700 text-sm">
            <strong>Verbindungsfehler:</strong> {{ $connectionStatus['message'] }}
        </div>
    @endif

    {{-- ─── Kalender-Tabelle ──────────────────────────────────────────── --}}
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm mb-8">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900">Kalender</h2>
            <span class="text-sm text-gray-500">{{ $kalender->whereNull('deleted_at')->count() }} aktiv</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">Name / Farbe</th>
                        <th class="px-4 py-3 text-left">CalDAV-URL</th>
                        <th class="px-4 py-3 text-center">Sichtbar</th>
                        <th class="px-4 py-3 text-center">Schreibbar</th>
                        <th class="px-4 py-3 text-left">Gruppen</th>
                        <th class="px-4 py-3 text-center">Termine</th>
                        <th class="px-4 py-3 text-left">Letzte Sync</th>
                        <th class="px-4 py-3 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($kalender as $cal)
                        <tr class="{{ $cal->trashed() ? 'bg-gray-50 opacity-60' : 'hover:bg-gray-50' }} transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full shrink-0 border border-gray-300"
                                          style="background-color: {{ $cal->farbe }}"></span>
                                    <span class="font-medium text-gray-900">{{ $cal->name }}</span>
                                    @if($cal->trashed())
                                        <span class="px-1.5 py-0.5 text-xs rounded bg-gray-200 text-gray-500">gelöscht</span>
                                    @endif
                                </div>
                                @if($cal->beschreibung)
                                    <p class="text-xs text-gray-400 mt-0.5 truncate max-w-[200px]">{{ $cal->beschreibung }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">
                                <span class="font-mono text-xs truncate block max-w-[220px]" title="{{ $cal->ox_calendar_id }}">
                                    {{ $cal->ox_calendar_id }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($cal->sichtbar)
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-green-100 text-green-600">✓</span>
                                @else
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 text-gray-400">–</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($cal->schreibbar)
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-100 text-blue-600">✓</span>
                                @else
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 text-gray-400">–</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($cal->groups as $group)
                                        <span class="px-1.5 py-0.5 text-xs rounded-full border
                                            {{ $group->pivot->schreibbar ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-gray-100 border-gray-200 text-gray-600' }}">
                                            {{ $group->name }}
                                            @if($group->pivot->schreibbar)
                                                <span class="ml-0.5 text-blue-400">✎</span>
                                            @endif
                                        </span>
                                    @empty
                                        <span class="text-xs text-gray-400">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">
                                {{ $cal->termine_count }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs">
                                @if($cal->letzte_synchronisation)
                                    <span title="{{ $cal->letzte_synchronisation->format('d.m.Y H:i:s') }}">
                                        {{ $cal->letzte_synchronisation->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if(!$cal->trashed())
                                    <div class="flex items-center justify-end gap-1">
                                        {{-- Bearbeiten --}}
                                        <button type="button"
                                                onclick="openEditModal({{ $cal->id }}, {{ json_encode($cal->only(['ox_calendar_id','name','farbe','beschreibung','sichtbar','schreibbar'])) }})"
                                                class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-200 transition-colors">
                                            Bearbeiten
                                        </button>
                                        {{-- Gruppen --}}
                                        <button type="button"
                                                onclick="openGruppenModal({{ $cal->id }}, {{ json_encode($cal->name) }}, {{ json_encode($cal->groups->map(fn($g) => ['group_id' => $g->id, 'schreibbar' => (bool)$g->pivot->schreibbar])) }})"
                                                class="px-2 py-1 text-xs rounded bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 transition-colors">
                                            Gruppen
                                        </button>
                                        {{-- Löschen --}}
                                        <form action="{{ route('calendar.admin.destroy', $cal) }}" method="POST"
                                              data-name="{{ $cal->name }}"
                                              onsubmit="return confirm('Kalender »' + this.dataset.name + '« wirklich löschen?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="px-2 py-1 text-xs rounded bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 transition-colors">
                                                Löschen
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-400">
                                Noch keine Kalender vorhanden.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ─── Neuen Kalender hinzufügen ─────────────────────────────────── --}}
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Neuen Kalender hinzufügen</h2>
        </div>
        <form action="{{ route('calendar.admin.store') }}" method="POST" class="px-5 py-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CalDAV-URL *</label>
                    <input type="text" name="ox_calendar_id" value="{{ old('ox_calendar_id') }}" required
                           placeholder="/caldav/users/dienst@ox.example.com/calendar/"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300 font-mono @error('ox_calendar_id') border-red-400 @enderror">
                    @error('ox_calendar_id')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           placeholder="Schulkalender"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300 @error('name') border-red-400 @enderror">
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Farbe *</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="farbe" value="{{ old('farbe', '#3B82F6') }}"
                               class="h-9 w-16 rounded border border-gray-300 cursor-pointer p-0.5">
                        <input type="text" id="farbe_text_new" value="{{ old('farbe', '#3B82F6') }}"
                               placeholder="#3B82F6"
                               class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-300 @error('farbe') border-red-400 @enderror"
                               oninput="document.querySelector('[name=farbe]').value = this.value">
                    </div>
                    @error('farbe')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                    <input type="text" name="beschreibung" value="{{ old('beschreibung') }}"
                           placeholder="Optionale Beschreibung"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>
                <div class="flex items-center gap-6 md:col-span-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="sichtbar" value="0">
                        <input type="checkbox" name="sichtbar" value="1" {{ old('sichtbar', '1') ? 'checked' : '' }}
                               class="w-4 h-4 rounded accent-blue-600">
                        <span class="text-sm text-gray-700">Sichtbar für Nutzer</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="schreibbar" value="0">
                        <input type="checkbox" name="schreibbar" value="1" {{ old('schreibbar') ? 'checked' : '' }}
                               class="w-4 h-4 rounded accent-blue-600">
                        <span class="text-sm text-gray-700">Global schreibbar</span>
                    </label>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-blue-300">
                    Kalender anlegen
                </button>
            </div>
        </form>
    </div>

</div>

{{-- ─── Bearbeiten-Modal ────────────────────────────────────────────── --}}
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900">Kalender bearbeiten</h3>
            <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <form id="editForm" method="POST" class="px-6 py-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CalDAV-URL *</label>
                    <input type="text" name="ox_calendar_id" id="edit_ox_calendar_id" required
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                    <input type="text" name="name" id="edit_name" required
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Farbe *</label>
                    <div class="flex items-center gap-2">
                        <input type="color" id="edit_farbe_picker"
                               class="h-9 w-16 rounded border border-gray-300 cursor-pointer p-0.5"
                               oninput="document.getElementById('edit_farbe_text').value = this.value; document.querySelector('#editForm [name=farbe]').value = this.value">
                        <input type="text" id="edit_farbe_text"
                               placeholder="#3B82F6"
                               class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-300"
                               oninput="document.getElementById('edit_farbe_picker').value = this.value; document.querySelector('#editForm [name=farbe]').value = this.value">
                        <input type="hidden" name="farbe" id="edit_farbe">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                    <input type="text" name="beschreibung" id="edit_beschreibung"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="sichtbar" value="0">
                        <input type="checkbox" name="sichtbar" id="edit_sichtbar" value="1"
                               class="w-4 h-4 rounded accent-blue-600">
                        <span class="text-sm text-gray-700">Sichtbar</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="schreibbar" value="0">
                        <input type="checkbox" name="schreibbar" id="edit_schreibbar" value="1"
                               class="w-4 h-4 rounded accent-blue-600">
                        <span class="text-sm text-gray-700">Schreibbar</span>
                    </label>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" onclick="closeEditModal()"
                        class="px-4 py-2 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                    Abbrechen
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm rounded-md bg-blue-600 hover:bg-blue-700 text-white font-medium">
                    Speichern
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ─── Gruppen-Modal ───────────────────────────────────────────────── --}}
<div id="gruppenModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white">
            <h3 class="text-base font-semibold text-gray-900">Gruppen-Zuordnung: <span id="gruppenModalTitle"></span></h3>
            <button type="button" onclick="closeGruppenModal()" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <form id="gruppenForm" method="POST" class="px-6 py-4">
            @csrf
            <p class="text-sm text-gray-500 mb-3">
                Wähle die Gruppen aus, die Zugriff auf diesen Kalender haben sollen.
                Aktiviere „Schreiben", damit die Gruppe Termine erstellen/bearbeiten kann.
            </p>
            <div class="flex flex-col gap-2">
                @foreach($gruppen as $gruppe)
                    <label class="flex items-center justify-between p-2.5 rounded-md border border-gray-200 hover:bg-gray-50 cursor-pointer group-row"
                           data-group-id="{{ $gruppe->id }}">
                        <div class="flex items-center gap-2">
                            <input type="checkbox"
                                   name="gruppen_check[]"
                                   value="{{ $gruppe->id }}"
                                   class="w-4 h-4 rounded accent-blue-600 group-check"
                                   onchange="toggleSchreibbarVisibility(this)">
                            <span class="text-sm text-gray-800">{{ $gruppe->name }}</span>
                        </div>
                        <label class="flex items-center gap-1.5 text-xs text-gray-500 schreibbar-toggle hidden">
                            <input type="checkbox"
                                   name="gruppen_schreibbar[]"
                                   value="{{ $gruppe->id }}"
                                   class="w-3.5 h-3.5 rounded accent-indigo-500">
                            <span>Schreiben</span>
                        </label>
                    </label>
                @endforeach
            </div>
            <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                <button type="button" onclick="closeGruppenModal()"
                        class="px-4 py-2 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                    Abbrechen
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm rounded-md bg-indigo-600 hover:bg-indigo-700 text-white font-medium">
                    Speichern
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('js')
<script>
    // ── Edit-Modal ──────────────────────────────────────────────────────────
    function openEditModal(id, data) {
        const form = document.getElementById('editForm');
        form.action = '/calendar/admin/kalender/' + id;

        document.getElementById('edit_ox_calendar_id').value  = data.ox_calendar_id;
        document.getElementById('edit_name').value            = data.name;
        document.getElementById('edit_farbe_picker').value    = data.farbe;
        document.getElementById('edit_farbe_text').value      = data.farbe;
        document.getElementById('edit_farbe').value           = data.farbe;
        document.getElementById('edit_beschreibung').value    = data.beschreibung ?? '';
        document.getElementById('edit_sichtbar').checked      = !!data.sichtbar;
        document.getElementById('edit_schreibbar').checked    = !!data.schreibbar;

        const modal = document.getElementById('editModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeEditModal() {
        const modal = document.getElementById('editModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // ── Gruppen-Modal ───────────────────────────────────────────────────────
    function openGruppenModal(id, name, assignedGroups) {
        document.getElementById('gruppenModalTitle').textContent = name;

        const form = document.getElementById('gruppenForm');
        form.action = '/calendar/admin/kalender/' + id + '/gruppen';

        // Alle Checkboxen zurücksetzen
        document.querySelectorAll('.group-check').forEach(cb => {
            cb.checked = false;
            const row = cb.closest('.group-row');
            row.querySelector('.schreibbar-toggle').classList.add('hidden');
        });

        // Zugewiesene Gruppen aktivieren
        assignedGroups.forEach(item => {
            const cb = document.querySelector(`.group-check[value="${item.group_id}"]`);
            if (cb) {
                cb.checked = true;
                const row = cb.closest('.group-row');
                const toggle = row.querySelector('.schreibbar-toggle');
                toggle.classList.remove('hidden');
                if (item.schreibbar) {
                    const schreibbarCb = toggle.querySelector('input[type=checkbox]');
                    if (schreibbarCb) schreibbarCb.checked = true;
                }
            }
        });

        const modal = document.getElementById('gruppenModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeGruppenModal() {
        const modal = document.getElementById('gruppenModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function toggleSchreibbarVisibility(checkbox) {
        const row = checkbox.closest('.group-row');
        const toggle = row.querySelector('.schreibbar-toggle');
        if (checkbox.checked) {
            toggle.classList.remove('hidden');
        } else {
            toggle.classList.add('hidden');
            toggle.querySelector('input[type=checkbox]').checked = false;
        }
    }

    // ── Gruppen-Formular: Daten als gruppen[]-Array serialisieren ────────────
    document.getElementById('gruppenForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const form = this;
        // Bestehende gruppen-Inputs entfernen
        form.querySelectorAll('input[name^="gruppen["]').forEach(el => el.remove());

        let idx = 0;
        form.querySelectorAll('.group-check:checked').forEach(cb => {
            const groupId  = cb.value;
            const row      = cb.closest('.group-row');
            const schreibbarCb = row.querySelector('.schreibbar-toggle input[type=checkbox]');
            const schreibbar   = schreibbarCb && schreibbarCb.checked ? '1' : '0';

            const idInput = document.createElement('input');
            idInput.type  = 'hidden';
            idInput.name  = `gruppen[${idx}][group_id]`;
            idInput.value = groupId;
            form.appendChild(idInput);

            const swInput = document.createElement('input');
            swInput.type  = 'hidden';
            swInput.name  = `gruppen[${idx}][schreibbar]`;
            swInput.value = schreibbar;
            form.appendChild(swInput);

            idx++;
        });

        form.submit();
    });

    // ── Farb-Picker sync ────────────────────────────────────────────────────
    document.querySelector('[name=farbe]').addEventListener('input', function () {
        document.getElementById('farbe_text_new').value = this.value;
    });
</script>
@endpush



