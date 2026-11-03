{{-- ICS-Import: Datei hochladen → Vorschau --}}
<div x-show="showImportModal"
     x-cloak
     class="cal-modal-backdrop"
     @click.self="showImportModal = false"
     @keydown.escape.window="showImportModal && (showImportModal = false)">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6" @click.stop
         x-data="{ dateiname: '', sendet: false }">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Termine importieren</h2>
            <button type="button" @click="showImportModal = false"
                    class="w-8 h-8 inline-flex items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    aria-label="Schließen">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form action="{{ route('calendar.import.preview') }}" method="POST" enctype="multipart/form-data"
              @submit="sendet = true">
            @csrf

            <label for="import-datei"
                   class="flex flex-col items-center justify-center gap-2 px-4 py-8 border-2 border-dashed rounded-lg cursor-pointer transition-colors"
                   :class="dateiname ? 'border-blue-400 bg-blue-50' : 'border-gray-300 hover:border-blue-300 hover:bg-gray-50'">
                <i class="fas fa-file-upload text-2xl" :class="dateiname ? 'text-blue-600' : 'text-gray-400'"></i>
                <span class="text-sm text-gray-700 text-center" x-text="dateiname || '.ics-Datei auswählen'"></span>
                <span class="text-xs text-gray-500">z. B. Export aus Outlook, Google oder einem Ferienkalender · max. 5 MB</span>
                <input id="import-datei" type="file" name="datei" accept=".ics,text/calendar" required class="sr-only"
                       @change="dateiname = $event.target.files[0]?.name || ''">
            </label>
            @error('datei')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <p class="mt-3 text-xs text-gray-500 leading-snug">
                Im nächsten Schritt siehst du alle enthaltenen Termine, kannst auswählen, welche übernommen werden,
                Zielkalender festlegen und Hinweise ergänzen.
            </p>

            <div class="flex justify-end gap-2 mt-5">
                <button type="button" @click="showImportModal = false"
                        class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 rounded-md">
                    Abbrechen
                </button>
                <button type="submit" :disabled="!dateiname || sendet"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-md">
                    <span x-show="!sendet">Weiter zur Vorschau</span>
                    <span x-show="sendet" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i>Liest Datei…</span>
                </button>
            </div>
        </form>
    </div>
</div>
