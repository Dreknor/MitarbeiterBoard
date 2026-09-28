{{--
    Teilnehmer-Picker (Alpine.js)
    Erwartet:
      $options   – ['users' => [...], 'groups' => [...], 'roles' => [...]] (je id + name)
      $selection – ['users' => [ids], 'organizers' => [ids], 'groups' => [ids], 'roles' => [ids]]
    Erzeugt die Felder users[], organizers[], groups[], roles[].
--}}
<div x-data="meetingParticipantPicker(@js($options), @js($selection))" class="relative">
    <div class="flex items-center justify-between gap-2 mb-1">
        <span class="mtg-label mb-0">Teilnehmende</span>
        <span class="text-xs text-gray-400" x-text="summary"></span>
    </div>

    <div class="mtg-tabs mb-2" role="tablist">
        <template x-for="t in tabs" :key="t.key">
            <button type="button" class="mtg-tab" :class="{ 'is-active': tab === t.key }"
                    role="tab" :aria-selected="(tab === t.key).toString()"
                    @click="tab = t.key; query = ''; open = true; $nextTick(() => $refs.search.focus())">
                <i :class="t.icon"></i>
                <span x-text="t.label"></span>
            </button>
        </template>
    </div>

    <div class="relative" @click.outside="open = false">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
        <input type="search" x-ref="search" class="mtg-input" style="padding-left: 2rem"
               x-model="query" :placeholder="placeholder" autocomplete="off"
               @focus="open = true" @input="open = true; highlighted = 0"
               @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
               @keydown.enter.prevent="pick(results[highlighted])" @keydown.escape.stop="open = false">

        <div class="mtg-picker-results" x-show="open && results.length" x-transition.opacity style="display:none;">
            <template x-for="(item, index) in results" :key="tab + item.id">
                <button type="button" class="mtg-picker-option" :class="{ 'is-highlighted': index === highlighted }"
                        @mouseenter="highlighted = index" @click="pick(item)">
                    <i :class="currentTab.icon" class="text-gray-400 w-4 text-center"></i>
                    <span x-text="item.name" class="flex-1"></span>
                    <i class="fas fa-plus text-xs text-gray-300"></i>
                </button>
            </template>
        </div>
        <p class="mtg-hint" x-show="open && query.length && !results.length" style="display:none;">Keine Treffer.</p>
    </div>

    <div class="mtg-chips" x-show="hasSelection" style="display:none;">
        <template x-for="u in chosen('users')" :key="'u' + u.id">
            <span class="mtg-chip mtg-chip-user">
                <i class="fas" :class="isOrganizer(u.id) ? 'fa-crown text-amber-500' : 'fa-user'"></i>
                <span x-text="u.name"></span>
                <button type="button" @click="toggleOrganizer(u.id)"
                        :title="isOrganizer(u.id) ? 'Organisationsrecht entziehen' : 'Als Organisator:in festlegen'"
                        :aria-label="isOrganizer(u.id) ? 'Organisationsrecht entziehen' : 'Als Organisator:in festlegen'">
                    <i class="text-[10px]" :class="isOrganizer(u.id) ? 'fas fa-star' : 'far fa-star'"></i>
                </button>
                <button type="button" @click="remove('users', u.id)" aria-label="Entfernen">&times;</button>
            </span>
        </template>
        <template x-for="g in chosen('groups')" :key="'g' + g.id">
            <span class="mtg-chip mtg-chip-group">
                <i class="fas fa-users"></i>
                <span x-text="g.name"></span>
                <button type="button" @click="remove('groups', g.id)" aria-label="Entfernen">&times;</button>
            </span>
        </template>
        <template x-for="r in chosen('roles')" :key="'r' + r.id">
            <span class="mtg-chip mtg-chip-role">
                <i class="fas fa-id-badge"></i>
                <span x-text="r.name"></span>
                <button type="button" @click="remove('roles', r.id)" aria-label="Entfernen">&times;</button>
            </span>
        </template>
    </div>
    <p class="mtg-hint">
        <i class="far fa-star"></i> markiert Organisator:innen – sie dürfen das Meeting bearbeiten und einladen.
        Gruppen und Rollen laden alle zugehörigen Personen ein.
    </p>

    <template x-for="id in sel.users" :key="'hu' + id"><input type="hidden" name="users[]" :value="id"></template>
    <template x-for="id in sel.organizers" :key="'ho' + id"><input type="hidden" name="organizers[]" :value="id"></template>
    <template x-for="id in sel.groups" :key="'hg' + id"><input type="hidden" name="groups[]" :value="id"></template>
    <template x-for="id in sel.roles" :key="'hr' + id"><input type="hidden" name="roles[]" :value="id"></template>
</div>

@once
    @push('js')
        <script>
            window.meetingParticipantPicker = function (options, selection) {
                const norm = (s) => (s || '').toString().toLowerCase()
                    .normalize('NFD').replace(/[̀-ͯ]/g, '');

                return {
                    options,
                    sel: {
                        users: (selection.users || []).map(Number),
                        organizers: (selection.organizers || []).map(Number),
                        groups: (selection.groups || []).map(Number),
                        roles: (selection.roles || []).map(Number),
                    },
                    tabs: [
                        { key: 'users', label: 'Personen', icon: 'fas fa-user', placeholder: 'Person suchen …' },
                        { key: 'groups', label: 'Gruppen / Bereiche', icon: 'fas fa-users', placeholder: 'Gruppe oder Bereich suchen …' },
                        { key: 'roles', label: 'Rollen', icon: 'fas fa-id-badge', placeholder: 'Rolle suchen …' },
                    ],
                    tab: 'users',
                    query: '',
                    open: false,
                    highlighted: 0,

                    get currentTab() { return this.tabs.find(t => t.key === this.tab); },
                    get placeholder() { return this.currentTab.placeholder; },
                    get results() {
                        const q = norm(this.query);
                        return (this.options[this.tab] || [])
                            .filter(item => !this.sel[this.tab].includes(item.id))
                            .filter(item => q === '' || norm(item.name).includes(q))
                            .slice(0, 50);
                    },
                    get hasSelection() {
                        return this.sel.users.length + this.sel.groups.length + this.sel.roles.length > 0;
                    },
                    get summary() {
                        const parts = [];
                        if (this.sel.users.length) parts.push(this.sel.users.length + ' Person' + (this.sel.users.length === 1 ? '' : 'en'));
                        if (this.sel.groups.length) parts.push(this.sel.groups.length + ' Gruppe' + (this.sel.groups.length === 1 ? '' : 'n'));
                        if (this.sel.roles.length) parts.push(this.sel.roles.length + ' Rolle' + (this.sel.roles.length === 1 ? '' : 'n'));
                        return parts.join(' · ');
                    },
                    chosen(type) {
                        return this.sel[type]
                            .map(id => (this.options[type] || []).find(o => o.id === id))
                            .filter(Boolean);
                    },
                    move(step) {
                        if (!this.results.length) return;
                        this.open = true;
                        this.highlighted = (this.highlighted + step + this.results.length) % this.results.length;
                    },
                    pick(item) {
                        if (!item) return;
                        this.sel[this.tab].push(item.id);
                        this.query = '';
                        this.highlighted = 0;
                        this.$refs.search.focus();
                    },
                    remove(type, id) {
                        this.sel[type] = this.sel[type].filter(x => x !== id);
                        if (type === 'users') {
                            this.sel.organizers = this.sel.organizers.filter(x => x !== id);
                        }
                    },
                    isOrganizer(id) { return this.sel.organizers.includes(id); },
                    toggleOrganizer(id) {
                        this.sel.organizers = this.isOrganizer(id)
                            ? this.sel.organizers.filter(x => x !== id)
                            : [...this.sel.organizers, id];
                    },
                };
            };
        </script>
    @endpush
@endonce
