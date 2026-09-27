{{--
    Formular "Aufgabe vergeben" (Gruppen-Themen und Meeting-Themen).
    Erwartet: $action (URL), $users (Collection<User>), $allLabel (z. B. "Ganze Gruppe Schulleitung"),
              $ui ('thm' | 'mtg'), optional $cancel (Alpine-Ausdruck zum Schließen)
--}}
@php
    $ui       = $ui ?? 'thm';
    $selected = collect(old('users', []))->map(fn ($id) => (int) $id)->all();
    $people   = $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values();
@endphp
<form action="{{ $action }}" method="POST"
      x-data="{ assign: '{{ old('assign', 'all') }}', filter: '', selected: @js($selected), submitting: false,
                people: @js($people),
                get visible() { const q = this.filter.toLowerCase(); return this.people.filter(p => !q || p.name.toLowerCase().includes(q)); },
                toggle(id) { this.selected = this.selected.includes(id) ? this.selected.filter(x => x !== id) : [...this.selected, id]; } }"
      @submit="if (submitting) { $event.preventDefault(); return; } submitting = true">
    @csrf
    <div class="space-y-4">
        <div>
            <label class="{{ $ui }}-label" for="{{ $ui }}_task_text">Aufgabe <span class="text-red-500">*</span></label>
            <textarea name="task" id="{{ $ui }}_task_text" rows="2" maxlength="1000" required class="{{ $ui }}-textarea"
                      placeholder="Was ist zu tun?">{{ old('task') }}</textarea>
        </div>
        <div>
            <label class="{{ $ui }}-label" for="{{ $ui }}_task_date">Zu erledigen bis <span class="text-red-500">*</span></label>
            <input type="date" name="date" id="{{ $ui }}_task_date" required class="{{ $ui }}-input"
                   min="{{ now()->addDay()->format('Y-m-d') }}" value="{{ old('date', now()->addWeek()->format('Y-m-d')) }}">
        </div>

        <div>
            <span class="{{ $ui }}-label">Zuständig</span>
            <div class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-gray-100" role="radiogroup">
                <label class="flex items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold cursor-pointer transition-colors" style="display:flex; margin:0"
                       :class="assign === 'all' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-600'">
                    <input type="radio" name="assign" value="all" x-model="assign" class="sr-only">
                    <i class="fas fa-users"></i> {{ $allLabel }}
                </label>
                <label class="flex items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold cursor-pointer transition-colors" style="display:flex; margin:0"
                       :class="assign === 'users' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-600'">
                    <input type="radio" name="assign" value="users" x-model="assign" class="sr-only">
                    <i class="fas fa-user-check"></i> Ausgewählte Personen
                </label>
            </div>
            <p class="text-xs text-gray-500 mt-1.5" x-show="assign === 'all'">
                Eine gemeinsame Aufgabe – jede Person hakt ihren Teil ab, der Fortschritt ist für alle sichtbar.
            </p>

            <div x-show="assign === 'users'" style="display:none;" class="mt-2">
                <input type="search" x-model="filter" class="{{ $ui }}-input" placeholder="Person suchen …" aria-label="Personen filtern">
                <div class="mt-2 max-h-52 overflow-y-auto rounded-xl border-[1px] border-gray-200 divide-y divide-gray-100">
                    <template x-for="p in visible" :key="p.id">
                        <label class="flex items-center gap-2.5 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50" style="display:flex; margin:0">
                            <input type="checkbox" :checked="selected.includes(p.id)" @change="toggle(p.id)" class="accent-blue-600">
                            <span x-text="p.name" class="text-gray-800"></span>
                        </label>
                    </template>
                    <p x-show="!visible.length" class="px-3 py-2 text-sm text-gray-400">Keine Treffer.</p>
                </div>
                <template x-for="id in selected" :key="'sel' + id">
                    <input type="hidden" name="users[]" :value="id" :disabled="assign !== 'users'">
                </template>
                <p class="text-xs text-gray-500 mt-1.5">
                    <span x-text="selected.length"></span> ausgewählt ·
                    eine Person = persönliche Aufgabe, mehrere = gemeinsame Aufgabe mit Fortschritt.
                </p>
            </div>
        </div>

        <p class="text-xs text-gray-500">
            <i class="fas fa-shield-alt"></i> Wer für eine gleichlautende offene Aufgabe zu diesem Thema schon zuständig ist, bekommt sie nicht doppelt.
        </p>
    </div>

    <div class="flex justify-end gap-2 mt-5">
        @isset($cancel)
            <button type="button" class="{{ $ui }}-btn {{ $ui }}-btn-secondary" @click="{{ $cancel }}">Abbrechen</button>
        @endisset
        <button type="submit" class="{{ $ui }}-btn {{ $ui }}-btn-primary" :disabled="submitting || (assign === 'users' && !selected.length)">
            <i class="fas" :class="submitting ? 'fa-circle-notch fa-spin' : 'fa-check'"></i> Aufgabe vergeben
        </button>
    </div>
</form>
