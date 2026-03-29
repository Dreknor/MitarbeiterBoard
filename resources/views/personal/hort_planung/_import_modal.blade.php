{{-- Modal: Excel-Import (2-Schritt-Assistent, §8.1) --}}
<div id="importModal" class="hp-modal-overlay hidden" x-data
     @keydown.escape.window="document.getElementById('importModal').classList.add('hidden')">
    <div class="hp-modal-box" style="max-width:600px;" @click.stop
         x-data="hortImportAssistent()">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-800">
                Excel-Import
                <span x-show="schritt === 2" x-cloak class="text-gray-400 font-normal ml-1">– Personen zuordnen</span>
            </h3>
            <button @click="document.getElementById('importModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- ── Schritt 1: Datei-Upload ─────────────────────────────────── --}}
        <div x-show="schritt === 1" class="p-5 space-y-4">
            <p class="text-sm text-gray-500">
                Importiert eine Excel-Datei im Format der Original-Planung
                <span class="font-mono text-xs text-gray-400">(Planung_Hortstunden.xlsx)</span>.
                Personen werden im nächsten Schritt zugeordnet.
            </p>

            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center
                        hover:border-blue-400 transition-colors"
                 @dragover.prevent @drop.prevent="dateiAuswaehlen($event.dataTransfer.files[0])">
                <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <label class="cursor-pointer">
                    <input type="file" accept=".xlsx,.xls" class="sr-only"
                           @change="dateiAuswaehlen($event.target.files[0])" x-ref="fileInput">
                    <span x-show="!dateiName" class="text-sm text-gray-500">
                        Datei hier ablegen oder <span class="text-blue-600 hover:underline">auswählen</span>
                    </span>
                    <span x-show="dateiName" x-text="dateiName" x-cloak class="text-sm font-medium text-gray-700"></span>
                </label>
                <p class="text-xs text-gray-400 mt-1">Erlaubte Formate: .xlsx, .xls – max. 10 MB</p>
            </div>

            <div x-show="fehler" x-cloak class="flex gap-2 p-3 bg-red-50 rounded-xl">
                <p x-text="fehler" class="text-xs text-red-700"></p>
            </div>

            <div class="flex gap-3">
                <button type="button" @click="parseSchritt1()"
                        :disabled="!dateiObjekt || laedt"
                        class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50
                               text-white font-semibold rounded-xl text-sm">
                    <span x-show="!laedt">Datei analysieren →</span>
                    <span x-show="laedt" x-cloak>Analysiere …</span>
                </button>
                <button type="button"
                        @click="document.getElementById('importModal').classList.add('hidden')"
                        class="px-4 py-2.5 bg-white border border-gray-300 hover:bg-gray-50
                               text-gray-700 font-medium rounded-xl text-sm">
                    Abbrechen
                </button>
            </div>
        </div>

        {{-- ── Schritt 2: Personen-Mapping + Planungsdetails ───────────── --}}
        <div x-show="schritt === 2" x-cloak>
            <form action="{{ route('hort-planung.import') }}" method="POST"
                  enctype="multipart/form-data" @submit="laedt = true">
                @csrf
                {{-- Datei nochmals mitsenden --}}
                <input type="file" name="file" x-ref="hiddenFile" class="sr-only">

                <div class="p-5 space-y-4">
                    <div class="p-3 bg-blue-50 rounded-xl">
                        <p class="text-xs font-semibold text-blue-700 mb-1">Erkannte Monate</p>
                        <p class="text-sm text-blue-800" x-text="erkannteMonateText"></p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                Name der Planung <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="name" required x-model="planungName"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                                          focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="z.B. Hortstunden-Planung 2024–2027">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                Abteilung <span class="text-red-500">*</span>
                            </label>
                            <select name="department_id" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                                           focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">– Bitte wählen –</option>
                                @foreach(\App\Models\Group::orderBy('name')->get() as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Beschreibung</label>
                            <input type="text" name="beschreibung"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                                          focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Optional">
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold text-gray-700 mb-2">
                            Personen zuordnen
                            <span class="text-gray-400 font-normal">(nicht zugeordnete werden übersprungen)</span>
                        </p>
                        <div class="overflow-auto max-h-56 border border-gray-200 rounded-xl">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-600">Excel-Name</th>
                                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-600">Mitarbeiter</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(person, idx) in personen" :key="idx">
                                        <tr class="border-t border-gray-100 hover:bg-gray-50">
                                            <td class="px-3 py-2 text-gray-700 text-xs" x-text="person.excel_name"></td>
                                            <td class="px-3 py-2">
                                                <select :name="'mapping[' + person.excel_name + ']'"
                                                        x-model="personen[idx].user_id"
                                                        class="w-full rounded-lg border border-gray-300
                                                               px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                    <option value="">– Überspringen –</option>
                                                    <template x-for="nutzer in alleNutzer" :key="nutzer.id">
                                                        <option :value="nutzer.id"
                                                                :selected="nutzer.id == person.user_id"
                                                                x-text="nutzer.name"></option>
                                                    </template>
                                                </select>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 px-5 pb-5">
                    <button type="button" @click="schritt = 1; fehler = ''"
                            class="px-4 py-2.5 bg-white border border-gray-300 hover:bg-gray-50
                                   text-gray-700 font-medium rounded-xl text-sm">
                        ← Zurück
                    </button>
                    <button type="submit" :disabled="laedt"
                            class="flex-1 py-2.5 bg-green-600 hover:bg-green-700 disabled:opacity-50
                                   text-white font-semibold rounded-xl text-sm"
                            @click="dateiUebertragen()">
                        <span x-show="!laedt">Import durchführen</span>
                        <span x-show="laedt" x-cloak>Importiere …</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function hortImportAssistent() {
    return {
        schritt: 1,
        dateiObjekt: null,
        dateiName: '',
        planungName: '',
        monate: [],
        personen: [],
        alleNutzer: [],
        laedt: false,
        fehler: '',

        get erkannteMonateText() {
            if (!this.monate.length) return '–';
            const fmt = d => new Date(d).toLocaleDateString('de-DE', { month: 'short', year: 'numeric' });
            return `${fmt(this.monate[0])} – ${fmt(this.monate[this.monate.length - 1])} (${this.monate.length} Monate)`;
        },

        dateiAuswaehlen(datei) {
            if (!datei) return;
            this.dateiObjekt = datei;
            this.dateiName = datei.name;
            this.fehler = '';
        },

        async parseSchritt1() {
            if (!this.dateiObjekt) return;
            this.laedt = true;
            this.fehler = '';
            const fd = new FormData();
            fd.append('file', this.dateiObjekt);
            fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
            try {
                const res = await fetch('{{ route('hort-planung.importParse') }}', { method: 'POST', body: fd });
                const json = await res.json();
                if (!json.success) { this.fehler = json.message || 'Fehler beim Parsen'; return; }
                this.monate = json.monate;
                this.personen = json.personen;
                this.alleNutzer = json.alle_nutzer;
                this.schritt = 2;
            } catch(e) {
                this.fehler = 'Netzwerkfehler beim Analysieren der Datei.';
            } finally {
                this.laedt = false;
            }
        },

        dateiUebertragen() {
            if (this.dateiObjekt && this.$refs.hiddenFile) {
                const dt = new DataTransfer();
                dt.items.add(this.dateiObjekt);
                this.$refs.hiddenFile.files = dt.files;
            }
        },
    };
}
</script>
