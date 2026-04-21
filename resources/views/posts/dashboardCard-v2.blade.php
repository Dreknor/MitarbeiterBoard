{{-- Nachrichten-Card v2 – nur Body-Inhalt (cardWrapper übernimmt Header) --}}

<div x-data="{
        showAll: false,
        showCreate: false,
        saving: false,
        form: { header: '', text: '', released: 1, groups: [] },
        errors: {},
        async submit() {
            if (this.saving) return;
            if (!this.form.header || this.form.groups.length === 0) {
                this.errors = {
                    header: !this.form.header ? ['Überschrift erforderlich'] : null,
                    groups: this.form.groups.length === 0 ? ['Mindestens eine Gruppe wählen'] : null,
                };
                return;
            }
            this.saving = true;
            this.errors = {};
            const fd = new FormData();
            fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
            fd.append('header', this.form.header);
            fd.append('text', this.form.text);
            fd.append('released', this.form.released ? 1 : 0);
            this.form.groups.forEach(g => fd.append('groups[]', g));
            try {
                const res = await fetch('{{ url('posts') }}', {
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
                    alert('Fehler beim Speichern der Nachricht.');
                }
            } catch (e) {
                alert('Netzwerkfehler beim Speichern.');
            } finally {
                this.saving = false;
            }
        }
     }">

    {{-- Action-Bar mit Neue-Nachricht-Button --}}
    @can('create posts')
        <div class="px-4 py-2 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                <i class="fas fa-newspaper mr-1 opacity-60"></i>
                {{ $posts->count() > 0 ? $posts->count() . ' ' . ($posts->count() === 1 ? 'Nachricht' : 'Nachrichten') : 'Keine Nachrichten' }}
            </span>
            <button @click="showCreate = !showCreate"
                    class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-medium bg-blue-600 text-white hover:bg-blue-700">
                <i class="fas fa-plus"></i>
                <span x-text="showCreate ? 'Abbrechen' : 'Neue Nachricht'"></span>
            </button>
        </div>

        {{-- Inline-Formular zum Erstellen --}}
        <div x-show="showCreate" x-cloak
             class="px-4 py-3 bg-gray-50 border-b border-gray-100 space-y-2">
            <input x-model="form.header" type="text" placeholder="Überschrift *"
                   class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            <template x-if="errors.header">
                <p class="text-xs text-red-600" x-text="errors.header[0]"></p>
            </template>
            <textarea x-model="form.text" rows="3" placeholder="Inhalt (optional)"
                      class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Empfänger-Gruppen *</label>
                <div class="max-h-32 overflow-y-auto border border-gray-200 rounded-lg px-2 py-1 bg-white">
                    @foreach(auth()->user()->groups_rel as $group)
                        <label class="flex items-center gap-2 py-0.5 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" x-model="form.groups" value="{{ $group->id }}"
                                   class="rounded border-gray-300 text-blue-600">
                            {{ $group->name }}
                        </label>
                    @endforeach
                </div>
                <template x-if="errors.groups">
                    <p class="text-xs text-red-600 mt-1" x-text="errors.groups[0]"></p>
                </template>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" x-model="form.released" :true-value="1" :false-value="0"
                       class="rounded border-gray-300 text-blue-600">
                Direkt veröffentlichen
            </label>

            <div class="flex gap-2 pt-1">
                <button @click="submit()" :disabled="saving"
                        class="flex-1 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                    <span x-show="!saving"><i class="fas fa-paper-plane mr-1"></i> Speichern</span>
                    <span x-show="saving" x-cloak><i class="fas fa-spinner fa-spin"></i> …</span>
                </button>
                <button @click="showCreate = false"
                        class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">
                    Abbrechen
                </button>
            </div>
            <p class="text-xs text-gray-400">
                Für Datei-Anhänge &amp; Rich-Text:
                <a href="{{ url('posts/create') }}" class="text-blue-600 hover:underline">Ausführliches Formular</a>
            </p>
        </div>
    @endcan

    @if($posts->count() > 0)
        <div class="divide-y divide-gray-100">
            @foreach($posts as $index => $post)
                @if($post->released == 1 || $post->author_id == auth()->id())
                    <div x-show="showAll || {{ $index < 3 ? 'true' : 'false' }}">
                        <div class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50">
                            <div class="shrink-0 w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-xs font-bold">
                                {{ strtoupper(substr($post->author->name ?? 'U', 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-800">{{ $post->header }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    {{ $post->author->name ?? '' }}
                                    &middot;
                                    {{ $post->created_at->diffForHumans() }}
                                    @if(!$post->released)
                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                            Entwurf
                                        </span>
                                    @endif
                                </div>
                                @if($post->text)
                                    <div class="text-xs text-gray-600 mt-1 line-clamp-2">
                                        {{ \Illuminate\Support\Str::limit(strip_tags($post->text), 140) }}
                                    </div>
                                @endif
                                @if(!$post->released && $post->author_id == auth()->id())
                                    <a href="{{ url('posts/'.$post->id.'/release') }}"
                                       class="inline-block mt-1 text-xs text-green-600 hover:text-green-800 font-medium no-underline">
                                        <i class="fas fa-check"></i> Jetzt veröffentlichen
                                    </a>
                                @endif
                            </div>
                            @if($post->created_at->gt(now()->subDays(1)))
                                <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                    Neu
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        @if($posts->count() > 3)
            <div class="px-4 py-2 border-t border-gray-100 text-center">
                <button @click="showAll = !showAll"
                        class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                    <span x-text="showAll ? 'Weniger anzeigen ↑' : '{{ $posts->count() - 3 }} weitere anzeigen ↓'"></span>
                </button>
            </div>
        @endif
    @else
        <div @cannot('create posts') data-card-empty="true" @endcannot
             class="px-4 py-8 text-center text-gray-400 text-sm">
            <i class="fas fa-newspaper text-2xl mb-2 block opacity-40"></i>
            <p>Keine Nachrichten vorhanden</p>
            @can('create posts')
                <button @click="showCreate = true"
                        class="mt-3 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-blue-600 text-white hover:bg-blue-700">
                    <i class="fas fa-plus"></i> Erste Nachricht verfassen
                </button>
            @endcan
        </div>
    @endif
</div>

