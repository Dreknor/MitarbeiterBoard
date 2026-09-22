{{-- Modal: Planung duplizieren --}}
<div id="duplicateModal" class="hp-modal-overlay hidden" x-data
     @keydown.escape.window="document.getElementById('duplicateModal').classList.add('hidden')">
    <div class="hp-modal-box" @click.stop>
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-800">Planung duplizieren</h3>
            <button onclick="document.getElementById('duplicateModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form id="duplicateForm" action="" method="POST" class="p-5 space-y-4">
            @csrf

            <p class="text-sm text-gray-500">
                Erstellt eine vollständige Kopie der Planung
                <strong id="duplicateSourceName" class="text-gray-700"></strong>
                inkl. aller Monate, Personen und Faktoren.
            </p>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Name des neuen Szenarios <span class="text-red-500">*</span>
                </label>
                <input type="text" id="duplicateName" name="name" required
                       class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung (optional)</label>
                <textarea name="beschreibung" rows="2"
                          class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"
                          placeholder="Annahmen / Szenarien dieses Szenarios …"></textarea>
            </div>

            <div class="flex gap-3 pt-1">
                <button type="submit"
                        class="flex-1 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-xl text-sm">
                    Duplizieren
                </button>
                <button type="button"
                        onclick="document.getElementById('duplicateModal').classList.add('hidden')"
                        class="px-4 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium rounded-xl text-sm">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>
</div>

