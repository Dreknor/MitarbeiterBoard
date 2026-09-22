{{-- Schnellzugriff Card (N10) --}}
{{-- Persönliche konfigurierbare Links --}}

<div x-data="{
    showAdd: false,
    newLabel: '',
    newUrl: '',
    newIcon: 'fas fa-link',
    addLink() {
        if (!this.newLabel || !this.newUrl) return;
        fetch('/dashboard/quicklinks', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
            },
            body: JSON.stringify({ label: this.newLabel, url: this.newUrl, icon: this.newIcon })
        }).then(() => window.location.reload());
    },
    deleteLink(id) {
        fetch('/dashboard/quicklinks/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
        }).then(() => window.location.reload());
    }
}">

    @if($quicklinks->isEmpty() && !isset($editMode))
        <div class="px-4 py-6 text-center text-gray-400 text-sm">
            <i class="fas fa-star text-2xl mb-2 block opacity-40"></i>
            <p class="mb-3">Keine Schnellzugriffe angelegt</p>
            <button @click="showAdd = true"
                    class="inline-flex items-center gap-1 px-3 py-2 text-xs font-medium text-blue-600
                           border border-blue-200 rounded-lg hover:bg-blue-50">
                <i class="fas fa-plus"></i> Ersten Link hinzufügen
            </button>
        </div>
    @else
        <div class="divide-y divide-gray-100">
            @foreach($quicklinks as $link)
                <div class="flex items-center gap-3 px-4 py-2.5 group">
                    <div class="shrink-0 w-7 h-7 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600">
                        <i class="{{ $link->icon ?? 'fas fa-link' }} text-xs"></i>
                    </div>
                    <a href="{{ $link->url }}"
                       class="flex-1 text-sm text-gray-800 hover:text-blue-600 no-underline truncate font-medium">
                        {{ $link->label }}
                    </a>
                    <button @click="deleteLink({{ $link->id }})"
                            class="shrink-0 text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Formular neuen Link hinzufügen --}}
    <div x-show="showAdd" x-cloak class="px-4 py-3 bg-gray-50 border-t border-gray-100">
        <div class="space-y-2">
            <input x-model="newLabel" type="text" placeholder="Bezeichnung (z.B. Mein Stundenzettel)"
                   class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            <input x-model="newUrl" type="url" placeholder="URL (z.B. /timesheets/42)"
                   class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            <label class="block text-xs font-medium text-gray-600 mb-1 mt-1">Icon auswählen</label>
            <select x-model="newIcon" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                <option value="fas fa-link">🔗 Link</option>
                <option value="fas fa-clock">⏰ Uhr</option>
                <option value="fas fa-calendar">📅 Kalender</option>
                <option value="fas fa-file">📄 Datei</option>
                <option value="fas fa-users">👥 Gruppe</option>
                <option value="fas fa-star">⭐ Stern</option>
                <option value="fas fa-home">🏠 Startseite</option>
                <option value="fas fa-cog">⚙ Einstellungen</option>
            </select>
            <div class="flex gap-2">
                <button @click="addLink()"
                        class="flex-1 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    Hinzufügen
                </button>
                <button @click="showAdd = false"
                        class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">
                    Abbrechen
                </button>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
        <span class="text-xs text-gray-400">{{ $quicklinks->count() }} {{ $quicklinks->count() === 1 ? 'Link' : 'Links' }}</span>
        <button @click="showAdd = !showAdd"
                class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 font-medium">
            <i class="fas fa-plus"></i> Link hinzufügen
        </button>
    </div>

</div>

