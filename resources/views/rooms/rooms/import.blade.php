<div class="space-y-4">
    <p class="text-sm text-gray-600">
        Es besteht die Möglichkeit, einen Plan aus Indiware zu importieren. Dazu muss die <strong>Export-Gesamt XML-Datei</strong> aus Indiware ausgewählt werden.
        Voraussetzung ist, dass sowohl die Zeitraster als auch die Räume in Indiware angelegt und beim Export ausgewählt wurden.
        <br><strong>Klassen und Lehrkräfte</strong> werden automatisch aus dem XML extrahiert und im Raumplan angezeigt.
        Manuell eingetragene Buchungen bleiben beim Import erhalten – nur Indiware-Einträge werden aktualisiert.
    </p>

    <form action="{{ url('rooms/import') }}" method="post" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" title="Sollen nicht vorhandene Räume automatisch erstellt werden?">
                    Räume erstellen?
                    <svg class="w-3.5 h-3.5 inline text-gray-400 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </label>
                <select class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" name="create_rooms">
                    <option value="0">Nein</option>
                    <option value="1">Ja</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" title="Sollen vor dem Import alle bisherigen Indiware-Buchungen gelöscht werden? Manuelle Buchungen bleiben erhalten.">
                    Indiware-Plan leeren vor Import?
                    <svg class="w-3.5 h-3.5 inline text-gray-400 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </label>
                <select class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" name="deletePlan">
                    <option value="0" selected>Nein</option>
                    <option value="1">Ja</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" title="Soll das Zeitraster aus der XML-Datei in die Stundenzeiten-Tabelle übernommen werden? Dies ist nötig, damit der Vertretungsplan-Import die korrekten Uhrzeiten verwendet.">
                    Zeitraster synchronisieren?
                    <svg class="w-3.5 h-3.5 inline text-gray-400 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </label>
                <select class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" name="sync_zeitraster">
                    <option value="0">Nein</option>
                    <option value="1" selected>Ja</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" title="In welches Zeitraster sollen die Stundenzeiten gespeichert werden?">
                Ziel-Zeitraster
                <svg class="w-3.5 h-3.5 inline text-gray-400 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </label>
            <select class="w-full sm:w-1/2 rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" name="zeitraster_id">
                <option value="">— Neues Zeitraster aus XML-Name erstellen —</option>
                @foreach(\App\Models\Zeitraster::orderByDesc('ist_standard')->orderBy('name')->get() as $zr)
                    <option value="{{ $zr->id }}" @if($zr->ist_standard) selected @endif>
                        {{ $zr->name }}@if($zr->ist_standard) (Standard)@endif
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="file" class="block text-sm font-medium text-gray-700 mb-1">XML-Datei (Indiware Export-Gesamt)</label>
            <input type="file" name="file" id="file" class="customFile" accept="text/xml">
        </div>

        <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            Importieren
        </button>
    </form>
</div>
