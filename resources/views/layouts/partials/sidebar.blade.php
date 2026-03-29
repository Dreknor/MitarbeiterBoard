{{-- ============================================================
     SIDEBAR NAVIGATION – reines Tailwind CSS + Alpine.js
     ============================================================ --}}
<aside id="tw-sidebar" role="navigation" aria-label="Hauptnavigation">

    {{-- Logo / Branding --}}
    <div class="sidebar-logo">
        <a href="{{ config('app.url') }}" style="display:flex;align-items:center;justify-content:center;text-decoration:none;width:100%;">
            <img src="{{ asset('img/'.config('app.logo')) }}" alt="{{ env('APP_NAME') }}" style="width:100%;max-width:180px;height:auto;object-fit:contain;">
        </a>
        {{-- Mobile: Schließen-Button --}}
        <button id="sidebar-close-btn"
                style="position:absolute;top:0.5rem;right:0.5rem;background:none;border:none;color:rgba(255,255,255,0.6);cursor:pointer;font-size:1.25rem;padding:0.25rem;"
                aria-label="Sidebar schließen">
            <i class="fas fa-times"></i>
        </button>
    </div>

    {{-- Navigations-Inhalt --}}
    <nav class="sidebar-nav">
        @auth
            @cannot('disable menu')

                {{-- ── HAUPTBEREICH ────────────────────────────────────── --}}
                <div class="sidebar-section-label">Hauptmenü</div>

                {{-- Home --}}
                <a href="{{ url('/home') }}"
                   class="sidebar-link @if(request()->segment(1) == 'home' || request()->segment(1) == '') active @endif">
                    <i class="fa fa-home"></i>
                    <span>Home</span>
                </a>

                {{-- Wiki --}}
                @can('view wiki')
                    <a href="{{ url('/wiki') }}"
                       class="sidebar-link @if(request()->segment(1) == 'wiki') active @endif">
                        <i class="fa fa-book"></i>
                        <span>Wiki</span>
                    </a>
                @endcan

                <div class="sidebar-section-label">Pädagogik</div>

                {{-- Päd. Dokumentation / Wochenübersicht --}}
                @can('view paed diary')
                    <a href="{{ url('/paed-diary') }}"
                       class="sidebar-link @if(request()->segment(1) == 'paed-diary') active @endif">
                        <i class="fas fa-book-open"></i>
                        <span>Päd. Dokumentation</span>
                    </a>
                    <a href="{{ url('/display-week') }}"
                       class="sidebar-link @if(request()->segment(1) == 'display-week') active @endif">
                        <i class="fas fa-calendar-week"></i>
                        <span>Wochenübersicht</span>
                    </a>
                @endcan

                {{-- ── WOCHENPLÄNE (Untermenü) ─────────────────────────── --}}
                @canany(['view wochenplan', 'create wochenplan'])
                    @php $wpActive = request()->segment(1) == 'wp'; @endphp
                    <div x-data="{ open: {{ $wpActive ? 'true' : 'false' }} }">
                        <button class="sidebar-toggle @if($wpActive) active-parent @endif"
                                @click="open = !open"
                                :aria-expanded="open.toString()">
                            <i class="fas fa-th-list toggle-icon"></i>
                            <span class="toggle-label">Wochenpläne</span>
                            <i class="fas fa-chevron-down toggle-arrow"></i>
                        </button>
                        <div class="sidebar-submenu" x-show="open" x-collapse>
                            <a href="{{ route('wp.index') }}"
                               class="sidebar-link @if($wpActive && !request()->segment(2)) active @endif">
                                <i class="fas fa-list"></i>
                                <span>Übersicht</span>
                            </a>
                            @can('create wochenplan')
                                <a href="{{ route('wp.create') }}"
                                   class="sidebar-link @if($wpActive && request()->segment(2) == 'create') active @endif">
                                    <i class="fas fa-plus"></i>
                                    <span>Neuer Plan</span>
                                </a>
                                <a href="{{ route('wp.vorlagen.index') }}"
                                   class="sidebar-link @if($wpActive && request()->segment(2) == 'vorlagen') active @endif">
                                    <i class="fas fa-copy"></i>
                                    <span>Vorlagen</span>
                                </a>
                            @endcan
                            @can('manage wochenplan-faecher')
                                <a href="{{ route('wp.faecher.index') }}"
                                   class="sidebar-link @if($wpActive && request()->segment(2) == 'faecher') active @endif">
                                    <i class="fas fa-tags"></i>
                                    <span>Fächer</span>
                                </a>
                            @endcan
                            @can('manage wochenplan-formatvorlagen')
                                <a href="{{ route('wp.formatvorlagen.index') }}"
                                   class="sidebar-link @if($wpActive && request()->segment(2) == 'formatvorlagen') active @endif">
                                    <i class="fas fa-palette"></i>
                                    <span>Formatvorlagen</span>
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcanany


                {{-- ── DIAGNOSEBÖGEN (Untermenü) ───────────────────────── --}}
                @can('view diagnostics')
                    @php $diagActive = request()->segment(1) == 'diagnostics'; @endphp
                    <div x-data="{ open: {{ $diagActive ? 'true' : 'false' }} }">
                        <button class="sidebar-toggle @if($diagActive) active-parent @endif"
                                @click="open = !open"
                                :aria-expanded="open.toString()">
                            <i class="fas fa-clipboard-check toggle-icon"></i>
                            <span class="toggle-label">Diagnosebögen</span>
                            <i class="fas fa-chevron-down toggle-arrow"></i>
                        </button>
                        <div class="sidebar-submenu" x-show="open" x-collapse>
                            <a href="{{ route('diagnostic.index') }}"
                               class="sidebar-link @if($diagActive && request()->segment(2) != 'admin') active @endif">
                                <i class="fas fa-edit"></i>
                                <span>Erfassung</span>
                            </a>
                            @can('manage diagnostics')
                                <a href="{{ route('diagnostic.admin.index') }}"
                                   class="sidebar-link @if($diagActive && request()->segment(2) == 'admin') active @endif">
                                    <i class="fas fa-cog"></i>
                                    <span>Verwaltung</span>
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcan

                <div class="sidebar-section-label">Organisation</div>

                {{-- ── BERATUNGEN (dynamisch nach Gruppen) ────────────── --}}
                @php
                    $beratungSegments = ['themes', 'meetings', 'memory', 'archive', 'search', 'export'];
                    $beratungActive = in_array(request()->segment(2), $beratungSegments);
                @endphp
                <div x-data="{ open: {{ $beratungActive ? 'true' : 'false' }} }">
                    <button class="sidebar-toggle @if($beratungActive) active-parent @endif"
                            @click="open = !open"
                            :aria-expanded="open.toString()">
                        <i class="far fa-comments toggle-icon"></i>
                        <span class="toggle-label">Beratungen</span>
                        <i class="fas fa-chevron-down toggle-arrow"></i>
                    </button>
                    <div class="sidebar-submenu" x-show="open" x-collapse>

                        {{-- Globale Suche --}}
                        <a href="{{ url('/search') }}"
                           class="sidebar-link @if(request()->segment(1) == 'search') active @endif">
                            <i class="fa fa-search"></i>
                            <span>Globale Suche</span>
                        </a>

                        {{-- Pro Gruppe ein eigenes Untermenü --}}
                        @foreach(auth()->user()->groups() as $group)
                            @php
                                $groupActive = request()->segment(1) == $group->name;
                            @endphp
                            <div class="sidebar-sub-submenu" x-data="{ open: {{ $groupActive ? 'true' : 'false' }} }">
                                <button class="sidebar-toggle @if($groupActive) active-parent @endif"
                                        @click="open = !open"
                                        :aria-expanded="open.toString()">
                                    <i class="fas fa-users toggle-icon"></i>
                                    <span class="toggle-label">{{ $group->name }}</span>
                                    <i class="fas fa-chevron-down toggle-arrow"></i>
                                </button>
                                <div class="sidebar-submenu sidebar-sub-submenu" x-show="open" x-collapse>
                                    @if($group->use_meetings)
                                        <a href="{{ url($group->name.'/meetings') }}"
                                           class="sidebar-link @if($groupActive && request()->segment(2) == 'meetings' && request()->segment(3) != 'recurring') active @endif">
                                            <i class="fas fa-users"></i>
                                            <span>Meetings</span>
                                        </a>
                                    @endif
                                    <a href="{{ url($group->name.'/themes#'.\Carbon\Carbon::now()->format('Ymd')) }}"
                                       class="sidebar-link @if($groupActive && request()->segment(2) == 'themes' && request()->segment(3) != 'recurring') active @endif">
                                        <i class="far fa-comments"></i>
                                        <span>Themen</span>
                                    </a>
                                    <a href="{{ url($group->name.'/archive') }}"
                                       class="sidebar-link @if($groupActive && request()->segment(2) == 'archive') active @endif">
                                        <i class="fas fa-archive"></i>
                                        <span>Archiv</span>
                                    </a>
                                    <a href="{{ url($group->name.'/export') }}"
                                       class="sidebar-link @if($groupActive && request()->segment(2) == 'export') active @endif">
                                        <i class="fas fa-file-alt"></i>
                                        <span>Protokoll</span>
                                    </a>
                                    <a href="{{ url($group->name.'/search') }}"
                                       class="sidebar-link @if($groupActive && request()->segment(2) == 'search') active @endif">
                                        <i class="fas fa-search"></i>
                                        <span>Suche</span>
                                    </a>
                                    <a href="{{ url($group->name.'/memory') }}"
                                       class="sidebar-link @if($groupActive && request()->segment(2) == 'memory') active @endif">
                                        <i class="fas fa-save"></i>
                                        <span>Themenspeicher</span>
                                    </a>
                                    @can('create Wochenplan')
                                        @if($group->hasWochenplan == 1)
                                            <a href="{{ url($group->name.'/wochenplan') }}"
                                               class="sidebar-link @if($groupActive && request()->segment(2) == 'wochenplan') active @endif">
                                                <i class="fas fa-tasks"></i>
                                                <span>Wochenplan</span>
                                            </a>
                                        @endif
                                    @endcan
                                    @can('manage recurring themes')
                                        <a href="{{ url($group->name.'/themes/recurring') }}"
                                           class="sidebar-link @if($groupActive && request()->segment(3) == 'recurring' && request()->segment(2) == 'themes') active @endif">
                                            <i class="fas fa-redo"></i>
                                            <span>Wied. Themen</span>
                                        </a>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- ── PERSONAL (Untermenü) ────────────────────────────── --}}
                @canany(['create roster', 'edit employe', 'has timesheet', 'has holidays', 'approve holidays'])
                    @php
                        $personalActive = in_array(request()->segment(1), ['roster', 'timesheets', 'employes', 'holidays']);
                    @endphp
                    <div x-data="{ open: {{ $personalActive ? 'true' : 'false' }} }">
                        <button class="sidebar-toggle @if($personalActive) active-parent @endif"
                                @click="open = !open"
                                :aria-expanded="open.toString()">
                            <i class="fas fa-user-friends toggle-icon"></i>
                            <span class="toggle-label">Personal</span>
                            <i class="fas fa-chevron-down toggle-arrow"></i>
                        </button>
                        <div class="sidebar-submenu" x-show="open" x-collapse>
                            <a href="{{ route('employes.self') }}"
                               class="sidebar-link @if(request()->segment(1) == 'employes' && request()->segment(2) == 'self') active @endif">
                                <i class="fas fa-user"></i>
                                <span>Eigene Daten</span>
                            </a>
                            @can('create roster')
                                <a href="{{ route('roster.index') }}"
                                   class="sidebar-link @if(request()->segment(1) == 'roster') active @endif">
                                    <i class="fas fa-columns"></i>
                                    <span>Dienstpläne</span>
                                </a>
                            @endcan
                            @can('edit employe')
                                <a href="{{ route('employes.index') }}"
                                   class="sidebar-link @if(Route::currentRouteName() == 'employes.index' || Route::currentRouteName() == 'employes.show') active @endif">
                                    <i class="fas fa-users"></i>
                                    <span>Personal Übersicht</span>
                                </a>
                            @endcan
                            @can('lock timesheets')
                                <a href="{{ url('timesheets/select/employe') }}"
                                   class="sidebar-link @if(request()->segment(1) == 'timesheets' && request()->segment(2) != auth()->id() && request()->segment(2) != 'import') active @endif">
                                    <i class="fas fa-clock"></i>
                                    <span>Arbeitszeitnachweise</span>
                                </a>
                            @endcan
                            @can('has timesheet')
                                <a href="{{ url('timesheets/'.auth()->id()) }}"
                                   class="sidebar-link @if(request()->segment(1) == 'timesheets' && request()->segment(2) == auth()->id()) active @endif">
                                    <i class="fas fa-file-alt"></i>
                                    <span>Meine Zeitnachweise</span>
                                </a>
                            @endcan
                            @canany(['has holidays', 'approve holidays'])
                                <a href="{{ route('holidays.index') }}"
                                   class="sidebar-link @if(request()->segment(1) == 'holidays') active @endif">
                                    <i class="fas fa-umbrella-beach"></i>
                                    <span>Urlaub</span>
                                </a>
                            @endcanany
                            @can('view hort planung')
                                <a href="{{ route('hort-planung.index') }}"
                                   class="sidebar-link @if(request()->segment(1) == 'hort' && request()->segment(2) == 'planning') active @endif">
                                    <i class="fas fa-child"></i>
                                    <span>Hort Planung</span>
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcanany

                {{-- Kalender (OX-Integration) --}}
                @canany(['view calendar', 'manage calendar'])
                    @php $calendarActive = request()->segment(1) == 'calendar'; @endphp
                    <div x-data="{ open: {{ $calendarActive ? 'true' : 'false' }} }">
                        <button class="sidebar-toggle @if($calendarActive) active-parent @endif"
                                @click="open = !open"
                                :aria-expanded="open.toString()">
                            <i class="fa fa-calendar-alt toggle-icon"></i>
                            <span class="toggle-label">Kalender</span>
                            <i class="fas fa-chevron-down toggle-arrow"></i>
                        </button>
                        <div class="sidebar-submenu" x-show="open" x-collapse>
                            @can('view calendar')
                                <a href="{{ route('calendar.index') }}"
                                   class="sidebar-link @if($calendarActive && request()->segment(2) !== 'admin') active @endif">
                                    <i class="fa fa-calendar-alt"></i>
                                    <span>Kalenderansicht</span>
                                </a>
                            @endcan
                            @can('manage calendar')
                                <a href="{{ route('calendar.admin') }}"
                                   class="sidebar-link @if($calendarActive && request()->segment(2) == 'admin') active @endif">
                                    <i class="fas fa-cog"></i>
                                    <span>Verwaltung</span>
                                </a>
                                <a href="{{ route('calendar.admin.logs') }}"
                                   class="sidebar-link @if($calendarActive && request()->segment(2) == 'admin' && request()->segment(3) == 'logs') active @endif">
                                    <i class="fas fa-list-alt"></i>
                                    <span>Sync-Logs</span>
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcanany

                {{-- Raumplan --}}
                @can('view roomBooking')
                    <a href="{{ url('rooms/rooms') }}"
                       class="sidebar-link @if(request()->segment(1) == 'rooms') active @endif">
                        <i class="fa fa-calendar-alt"></i>
                        <span>Raumplan</span>
                    </a>
                @endcan


                {{-- ── TICKETSYSTEM (Untermenü) ────────────────────────── --}}
                @can('view tickets')
                    @php $ticketActive = request()->segment(1) == 'tickets'; @endphp
                    <div x-data="{ open: {{ $ticketActive ? 'true' : 'false' }} }">
                        <button class="sidebar-toggle @if($ticketActive) active-parent @endif"
                                @click="open = !open"
                                :aria-expanded="open.toString()">
                            <i class="fas fa-ticket-alt toggle-icon"></i>
                            <span class="toggle-label">Ticketsystem</span>
                            <i class="fas fa-chevron-down toggle-arrow"></i>
                        </button>
                        <div class="sidebar-submenu" x-show="open" x-collapse>
                            <a href="{{ url('tickets/') }}"
                               class="sidebar-link @if($ticketActive && !request()->segment(2)) active @endif">
                                <i class="fas fa-ticket-alt"></i>
                                <span>Offene Tickets</span>
                            </a>
                            <a href="{{ url('tickets/archiv') }}"
                               class="sidebar-link @if($ticketActive && request()->segment(2) == 'archiv') active @endif">
                                <i class="fas fa-archive"></i>
                                <span>Archiv</span>
                            </a>
                            @can('edit tickets')
                                <a href="{{ url('tickets/categories') }}"
                                   class="sidebar-link @if($ticketActive && request()->segment(2) == 'categories') active @endif">
                                    <i class="fas fa-folder-open"></i>
                                    <span>Kategorien</span>
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcan

                {{-- ── INVENTAR (Untermenü) ────────────────────────────── --}}
                @can('edit inventar')
                    @php $invActive = request()->segment(1) == 'inventory'; @endphp
                    <div x-data="{ open: {{ $invActive ? 'true' : 'false' }} }">
                        <button class="sidebar-toggle @if($invActive) active-parent @endif"
                                @click="open = !open"
                                :aria-expanded="open.toString()">
                            <i class="fas fa-boxes toggle-icon"></i>
                            <span class="toggle-label">Inventar</span>
                            <i class="fas fa-chevron-down toggle-arrow"></i>
                        </button>
                        <div class="sidebar-submenu" x-show="open" x-collapse>
                            <a href="{{ url('inventory/items') }}"
                               class="sidebar-link @if($invActive && request()->segment(2) == 'items') active @endif">
                                <i class="fas fa-dice-d6"></i>
                                <span>Inventar</span>
                            </a>
                            <a href="{{ url('inventory/locations') }}"
                               class="sidebar-link @if($invActive && request()->segment(2) == 'locations') active @endif">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Standorte</span>
                            </a>
                            <a href="{{ url('inventory/categories') }}"
                               class="sidebar-link @if($invActive && request()->segment(2) == 'categories') active @endif">
                                <i class="far fa-folder-open"></i>
                                <span>Kategorien</span>
                            </a>
                            <a href="{{ url('inventory/lieferanten') }}"
                               class="sidebar-link @if($invActive && request()->segment(2) == 'lieferanten') active @endif">
                                <i class="fas fa-shipping-fast"></i>
                                <span>Lieferanten</span>
                            </a>
                        </div>
                    </div>
                @endcan

                {{-- Prozesse --}}
                @can('manage procedures')
                    <a href="{{ url('/procedure') }}"
                       class="sidebar-link @if(request()->segment(1) == 'procedure') active @endif">
                        <i class="fas fa-project-diagram"></i>
                        <span>Prozesse</span>
                    </a>
                @elsecan('view assigned procedures')
                    <a href="{{ url('/procedure') }}"
                       class="sidebar-link @if(request()->segment(1) == 'procedure') active @endif">
                        <i class="fas fa-project-diagram"></i>
                        <span>Meine Prozesse</span>
                    </a>
                @endcan

                {{-- Listen --}}
                @can('see terminlisten')
                    <a href="{{ url('/listen') }}"
                       class="sidebar-link @if(request()->segment(1) == 'listen') active @endif">
                        <i class="fas fa-calendar"></i>
                        <span>Listen</span>
                    </a>
                @endcan


                {{-- ── VERTRETUNGSPLAN (Untermenü) ─────────────────────── --}}
                @can('edit vertretungen')
                    @php
                        $vertActive = in_array(request()->segment(1), ['vertretungen', 'dailyNews', 'weeks', 'abwesenheiten']);
                    @endphp
                    <div x-data="{ open: {{ $vertActive ? 'true' : 'false' }} }">
                        <button class="sidebar-toggle @if($vertActive) active-parent @endif"
                                @click="open = !open"
                                :aria-expanded="open.toString()">
                            <i class="fas fa-columns toggle-icon"></i>
                            <span class="toggle-label">Vertretungsplan</span>
                            <i class="fas fa-chevron-down toggle-arrow"></i>
                        </button>
                        <div class="sidebar-submenu" x-show="open" x-collapse>
                            <a href="{{ url('/vertretungen') }}"
                               class="sidebar-link @if(request()->segment(1) == 'vertretungen' && request()->segment(2) != 'archiv') active @endif">
                                <i class="fas fa-sync"></i>
                                <span>Vertretungen</span>
                            </a>
                            <a href="{{ url('/dailyNews') }}"
                               class="sidebar-link @if(request()->segment(1) == 'dailyNews') active @endif">
                                <i class="fas fa-newspaper"></i>
                                <span>Tages-News</span>
                            </a>
                            <a href="{{ url('/abwesenheiten') }}"
                               class="sidebar-link @if(request()->segment(1) == 'abwesenheiten') active @endif">
                                <i class="fas fa-user-slash"></i>
                                <span>Abwesenheiten</span>
                            </a>
                            <a href="{{ url('/weeks') }}"
                               class="sidebar-link @if(request()->segment(1) == 'weeks') active @endif">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Wochen</span>
                            </a>
                            <a href="{{ url('/vertretungen/archiv') }}"
                               class="sidebar-link @if(request()->segment(1) == 'vertretungen' && request()->segment(2) == 'archiv') active @endif">
                                <i class="fa fa-archive"></i>
                                <span>Archiv</span>
                            </a>
                        </div>
                    </div>
                @endcan

                {{-- ── VERWALTUNG (Abschnitt) ──────────────────────────── --}}
                @canany(['manage sick_notes', 'view old absences', 'edit klassen', 'manage grading systems',
                         'edit permissions', 'edit users', 'create types', 'edit settings', 'view logs'])
                    <div class="sidebar-divider"></div>
                    <div class="sidebar-section-label">Verwaltung</div>

                    {{-- Gruppen (immer sichtbar für eingeloggte User) --}}
                    <a href="{{ url('/groups') }}"
                       class="sidebar-link @if(request()->segment(1) == 'groups') active @endif">
                        <i class="fas fa-layer-group"></i>
                        <span>Gruppen</span>
                    </a>

                    @can('manage sick_notes')
                        <a href="{{ url('sick_notes') }}"
                           class="sidebar-link @if(request()->segment(1) == 'sick_notes') active @endif">
                            <i class="fas fa-notes-medical"></i>
                            <span>Krankschreibungen</span>
                        </a>
                    @endcan

                    @can('view old absences')
                        <a href="{{ url('absences') }}"
                           class="sidebar-link @if(request()->segment(1) == 'absences') active @endif">
                            <i class="fas fa-user-clock"></i>
                            <span>Abwesenheiten</span>
                        </a>
                    @endcan

                    @can('edit klassen')
                        <a href="{{ url('/klassen') }}"
                           class="sidebar-link @if(request()->segment(1) == 'klassen') active @endif">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Klassen</span>
                        </a>
                    @endcan

                    @can('manage grading systems')
                        <a href="{{ route('admin.grading.index') }}"
                           class="sidebar-link @if(request()->segment(1) == 'admin' && request()->segment(2) == 'grading') active @endif">
                            <i class="fas fa-layer-group"></i>
                            <span>Graduierungssysteme</span>
                        </a>
                    @endcan

                    @can('edit permissions')
                        <a href="{{ url('/roles') }}"
                           class="sidebar-link @if(request()->segment(1) == 'roles' && request()->segment(2) != 'user') active @endif">
                            <i class="fas fa-lock"></i>
                            <span>Rechte &amp; Rollen</span>
                        </a>
                    @endcan

                    @can('edit users')
                        <a href="{{ url('/users') }}"
                           class="sidebar-link @if(request()->segment(1) == 'users') active @endif">
                            <i class="fas fa-user-cog"></i>
                            <span>Benutzerverwaltung</span>
                        </a>
                    @endcan

                    @can('create types')
                        <a href="{{ url('/types') }}"
                           class="sidebar-link @if(request()->segment(1) == 'types') active @endif">
                            <i class="fas fa-comments"></i>
                            <span>Thementypen</span>
                        </a>
                    @endcan

                    @can('edit settings')
                        <a href="{{ url('/settings') }}"
                           class="sidebar-link @if(request()->segment(1) == 'settings') active @endif">
                            <i class="fas fa-sliders-h"></i>
                            <span>Einstellungen</span>
                        </a>
                    @endcan

                    @can('view logs')
                        <a href="{{ url('/logs') }}"
                           class="sidebar-link @if(request()->segment(1) == 'logs') active @endif">
                            <i class="fas fa-history"></i>
                            <span>Logs</span>
                        </a>
                    @endcan
                @endcanany

            @endcannot
        @endauth
    </nav>

</aside>

{{-- Mobile Overlay --}}
<div id="sidebar-overlay" aria-hidden="true"></div>

