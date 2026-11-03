{{-- ============================================================
     TOPBAR – reines Tailwind CSS + Alpine.js
     ============================================================ --}}
<header id="tw-topbar">

    {{-- Mobile: Hamburger-Button --}}
    <button id="sidebar-open-btn"
            style="background:none;border:none;cursor:pointer;color:#374151;font-size:1.25rem;padding:0.25rem 0.5rem;display:none;"
            class="mobile-menu-btn"
            aria-label="Menü öffnen">
        <i class="fas fa-bars"></i>
    </button>

    {{-- Desktop: Sidebar wieder einblenden (nur sichtbar wenn eingeklappt) --}}
    <button id="sidebar-reopen-btn"
            style="background:none;border:none;cursor:pointer;color:#374151;font-size:1.25rem;padding:0.25rem 0.5rem;align-items:center;gap:0.4rem;"
            title="Menü einblenden"
            aria-label="Menü einblenden">
        <i class="fas fa-bars"></i>
    </button>

    {{-- Seitentitel / App-Name --}}
    <span class="topbar-title">
        @hasSection('site-title')
            @yield('site-title')
        @else
            {{ env('APP_NAME') }}
        @endif
    </span>

    {{-- Rechts: Benachrichtigungs-Glocke --}}
    @auth
        <div style="position:relative;"
             x-data="{
                open: false,
                geladen: false,
                ungelesen: {{ (int) ($ungeleseneBenachrichtigungen ?? 0) }},
                eintraege: [],
                laden() {
                    fetch('{{ route('benachrichtigungen.neueste') }}', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(r => r.json())
                        .then(d => { this.eintraege = d.eintraege; this.ungelesen = d.ungelesen; this.geladen = true; })
                        .catch(() => { this.geladen = true; });
                },
                umschalten() { this.open = !this.open; if (this.open) this.laden(); },
                alleGelesen() {
                    fetch('{{ route('benachrichtigungen.gelesen') }}', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                    }).then(() => { this.ungelesen = 0; this.eintraege.forEach(e => e.gelesen = true); });
                }
             }"
             @keydown.escape.window="open = false">
            <button type="button" class="topbar-bell-btn"
                    @click="umschalten()"
                    :aria-expanded="open.toString()"
                    :aria-label="ungelesen > 0 ? ungelesen + ' ungelesene Benachrichtigungen' : 'Benachrichtigungen'"
                    title="Benachrichtigungen">
                <i class="fas fa-bell"></i>
                <span class="topbar-bell-badge" x-show="ungelesen > 0" x-text="ungelesen > 99 ? '99+' : ungelesen"
                      @if(!($ungeleseneBenachrichtigungen ?? 0)) style="display:none;" @endif>{{ ($ungeleseneBenachrichtigungen ?? 0) > 99 ? '99+' : ($ungeleseneBenachrichtigungen ?? 0) }}</span>
            </button>

            <div class="topbar-bell-panel" x-show="open" x-cloak @click.outside="open = false" style="display:none;">
                <div class="topbar-bell-head">
                    <span>Benachrichtigungen</span>
                    <button type="button" x-show="ungelesen > 0" @click="alleGelesen()">Alle als gelesen markieren</button>
                </div>
                <div class="topbar-bell-list">
                    <div class="topbar-bell-empty" x-show="!geladen">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <div class="topbar-bell-empty" x-show="geladen && eintraege.length === 0">
                        Keine Benachrichtigungen 🎉
                    </div>
                    <template x-for="e in eintraege" :key="e.id">
                        <a :href="e.url" class="topbar-bell-item" :class="{ 'is-unread': !e.gelesen }">
                            <span class="topbar-bell-icon"><i class="fas" :class="e.icon"></i></span>
                            <span style="min-width:0;flex:1;">
                                <span class="topbar-bell-title" style="display:block;" x-text="e.titel"></span>
                                <span class="topbar-bell-text" x-show="e.text && e.text !== e.titel" x-text="e.text"></span>
                                <span class="topbar-bell-time" style="display:block;" x-text="e.zeit"></span>
                            </span>
                        </a>
                    </template>
                </div>
                <div class="topbar-bell-foot">
                    <a href="{{ route('benachrichtigungen.index') }}">Alle anzeigen</a>
                    <a href="{{ route('benachrichtigungen.tag') }}">Mein Tag</a>
                    <a href="{{ route('benachrichtigungen.einstellungen') }}"><i class="fas fa-cog"></i> Einstellungen</a>
                </div>
            </div>
        </div>
    @endauth

    {{-- Rechts: User-Bereich --}}
    @auth
        <div style="position:relative;" x-data="{ open: false }">
            <button class="topbar-user-btn"
                    @click="open = !open"
                    @click.outside="open = false"
                    :aria-expanded="open.toString()">
                <img src="{{ auth()->user()->photo() }}"
                     alt="{{ auth()->user()->name }}"
                     style="width:28px;height:28px;border-radius:50%;object-fit:cover;">
                <span class="d-none d-md-inline" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    {{ auth()->user()->name }}
                </span>
                <i class="fas fa-chevron-down" style="font-size:0.65rem;opacity:0.6;"></i>
            </button>

            {{-- Dropdown-Menü --}}
            <div class="topbar-dropdown" x-show="open" x-cloak @click.outside="open = false"
                 style="display:none;">
                <div style="padding:0.75rem 1rem 0.5rem;">
                    <div style="font-weight:600;font-size:0.875rem;color:#111827;">{{ auth()->user()->name }}</div>
                    @if(auth()->user()->email)
                        <div style="font-size:0.75rem;color:#6b7280;margin-top:0.1rem;">{{ auth()->user()->email }}</div>
                    @endif
                </div>
                <div class="topbar-dropdown-divider"></div>
                <a href="{{ route('employes.self') }}" class="topbar-dropdown-item">
                    <i class="fas fa-user" style="width:1rem;opacity:0.6;"></i>
                    Eigene Daten
                </a>
                <a href="{{ route('benachrichtigungen.einstellungen') }}" class="topbar-dropdown-item">
                    <i class="fas fa-bell" style="width:1rem;opacity:0.6;"></i>
                    Benachrichtigungen
                </a>
                <div class="topbar-dropdown-divider"></div>
                <button class="topbar-dropdown-item"
                        onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                    <i class="fas fa-sign-out-alt" style="width:1rem;opacity:0.6;"></i>
                    Logout
                </button>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">
                    @csrf
                </form>
            </div>
        </div>
    @else
        <a href="{{ url('/login') }}"
           style="padding:0.4rem 1rem;background:#1a2035;color:white;border-radius:0.375rem;text-decoration:none;font-size:0.875rem;font-weight:500;">
            Login
        </a>
    @endauth

</header>

{{-- Mobile: CSS für den Hamburger-Button --}}
<style>
@media (max-width: 767px) {
    .mobile-menu-btn { display: block !important; }
}
</style>

