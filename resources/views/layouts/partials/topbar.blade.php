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

    {{-- Seitentitel / App-Name --}}
    <span class="topbar-title">
        @hasSection('site-title')
            @yield('site-title')
        @else
            {{ env('APP_NAME') }}
        @endif
    </span>

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

