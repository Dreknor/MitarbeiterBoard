{{-- Tagesinfos-Card – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
<div x-data="{
        showCreate: false,
        saving: false,
        form: { date_start: '{{ now()->format('Y-m-d') }}', date_end: '', news: '' },
        errors: {},
        async submit() {
            if (this.saving) return;
            if (!this.form.news || !this.form.date_start) {
                this.errors = {
                    news: !this.form.news ? ['Text erforderlich'] : null,
                    date_start: !this.form.date_start ? ['Startdatum erforderlich'] : null,
                };
                return;
            }
            this.saving = true;
            this.errors = {};
            const fd = new FormData();
            fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
            fd.append('date_start', this.form.date_start);
            if (this.form.date_end) fd.append('date_end', this.form.date_end);
            fd.append('news', this.form.news);
            try {
                const res = await fetch('{{ url('dailyNews') }}', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: fd,
                });
                if (res.ok || res.status === 302) {
                    window.location.reload();
                } else if (res.status === 422) {
                    const j = await res.json();
                    this.errors = j.errors || {};
                } else {
                    alert('Fehler beim Speichern.');
                }
            } catch (e) { alert('Netzwerkfehler.'); }
            finally { this.saving = false; }
        },
        async del(id) {
            if (!confirm('Tagesinfo wirklich löschen?')) return;
            const fd = new FormData();
            fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
            fd.append('_method', 'DELETE');
            await fetch('{{ url('dailyNews') }}/' + id, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            });
            window.location.reload();
        }
     }">

    @can('edit vertretungen')
        <div class="px-4 py-2 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                <i class="fas fa-bullhorn mr-1 opacity-60"></i>
                {{ $tagesinfos->count() }} {{ $tagesinfos->count() === 1 ? 'Tagesinfo' : 'Tagesinfos' }}
            </span>
            <button @click="showCreate = !showCreate"
                    class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-medium bg-blue-600 text-white hover:bg-blue-700">
                <i class="fas fa-plus"></i>
                <span x-text="showCreate ? 'Abbrechen' : 'Neue Info'"></span>
            </button>
        </div>

        <div x-show="showCreate" x-cloak class="px-4 py-3 bg-gray-50 border-b border-gray-100 space-y-2">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Von *</label>
                    <input x-model="form.date_start" type="date"
                           class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Bis (optional)</label>
                    <input x-model="form.date_end" type="date"
                           class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5">
                </div>
            </div>
            <textarea x-model="form.news" rows="2" placeholder="Info-Text *"
                      class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
            <template x-if="errors.news"><p class="text-xs text-red-600" x-text="errors.news[0]"></p></template>
            <div class="flex gap-2">
                <button @click="submit()" :disabled="saving"
                        class="flex-1 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                    <span x-show="!saving"><i class="fas fa-save mr-1"></i> Speichern</span>
                    <span x-show="saving" x-cloak><i class="fas fa-spinner fa-spin"></i> …</span>
                </button>
                <button @click="showCreate = false"
                        class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">Abbrechen</button>
            </div>
        </div>
    @endcan

    <div class="divide-y divide-gray-100">
        @forelse($tagesinfos as $news)
            <div class="flex items-start gap-3 px-4 py-3 group">
                <div class="shrink-0 text-xl">📢</div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm text-gray-800">{{ $news->news }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">
                        @if($news->date_end && $news->date_end->format('Y-m-d') !== $news->date_start->format('Y-m-d'))
                            {{ $news->date_start->format('d.m.Y') }} – {{ $news->date_end->format('d.m.Y') }}
                        @else
                            {{ $news->date_start->format('d.m.Y') }}
                        @endif
                    </div>
                </div>
                @can('edit vertretungen')
                    <button @click="del({{ $news->id }})"
                            class="shrink-0 text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity"
                            title="Löschen">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                @endcan
            </div>
        @empty
            <div @cannot('edit vertretungen') data-card-empty="true" @endcannot
                 class="px-4 py-8 text-center text-gray-400 text-sm">
                <i class="fas fa-bullhorn text-2xl mb-2 block opacity-40"></i>
                <p>Keine aktuellen Tagesinfos</p>
                @can('edit vertretungen')
                    <button @click="showCreate = true"
                            class="mt-3 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-blue-600 text-white hover:bg-blue-700">
                        <i class="fas fa-plus"></i> Erste Tagesinfo anlegen
                    </button>
                @endcan
            </div>
        @endforelse
    </div>
</div>

