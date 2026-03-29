{{-- Inline-Form: Neuen zeitlichen Faktor-Wert hinzufügen --}}
{{-- Eingebunden in _faktoren.blade.php pro Faktor --}}
<form action="{{ route('hort-planung.storeFaktorWert', [$planung, $faktor]) }}" method="POST"
      class="grid grid-cols-3 gap-2">
    @csrf
    <div>
        <label class="block text-xs text-gray-500 mb-1">Neuer Wert <span class="text-red-500">*</span></label>
        <input type="number" name="wert" step="0.000001" min="0" required
               class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-blue-500 outline-none font-mono">
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Gültig ab <span class="text-red-500">*</span></label>
        <input type="month" name="gueltig_ab" required
               class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-blue-500 outline-none">
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Notiz</label>
        <input type="text" name="notiz" placeholder="z. B. Gesetzesänderung 2026"
               class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-blue-500 outline-none">
    </div>
    <div class="col-span-3 flex justify-end gap-2">
        <button type="submit"
                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg">
            Wert speichern
        </button>
    </div>
</form>

