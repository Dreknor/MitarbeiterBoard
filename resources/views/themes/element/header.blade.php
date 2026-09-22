{{-- Themen-Board Kopf: Titel, Ansichtswechsel, Abo --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="thm-page-title text-2xl font-bold text-gray-900">
        Themen <span class="text-gray-400 font-semibold">{{ request()->segment(1) }}</span>
    </h1>

    <div class="flex items-center gap-2">
        {{-- Abo / Benachrichtigung --}}
        @if(!isset($subscription))
            <a href="{{ url('subscription/group/'.request()->segment(1)) }}" class="thm-btn thm-btn-secondary thm-btn-sm" title="Benachrichtigungen aktivieren">
                <i class="far fa-bell"></i>
            </a>
        @else
            <a href="{{ url('subscription/group/'.request()->segment(1).'/remove') }}" class="thm-btn thm-btn-primary thm-btn-sm" title="Benachrichtigungen deaktivieren">
                <i class="fas fa-bell"></i>
            </a>
        @endif

        {{-- Ansicht wechseln --}}
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button type="button" class="thm-btn thm-btn-secondary thm-btn-sm" @click="open = !open">
                <i class="fas fa-eye"></i>
                <span class="hidden sm:inline">Ansicht</span>
                <i class="fas fa-chevron-down text-xs" :class="open && 'rotate-180'"></i>
            </button>
            <div x-show="open" x-transition x-cloak style="display:none"
                 class="absolute right-0 mt-2 w-44 bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-40">
                <a href="{{ url(request()->segment(1).'/view/date') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 {{ $viewType=='date' ? 'text-blue-600 font-semibold' : 'text-gray-700' }}">
                    <i class="fas fa-calendar-day w-4 {{ $viewType=='date' ? '' : 'text-gray-400' }}"></i> Datum
                </a>
                <a href="{{ url(request()->segment(1).'/view/type') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 {{ $viewType=='type' ? 'text-blue-600 font-semibold' : 'text-gray-700' }}">
                    <i class="fas fa-tag w-4 {{ $viewType=='type' ? '' : 'text-gray-400' }}"></i> Typ
                </a>
                <a href="{{ url(request()->segment(1).'/view/priority') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 {{ $viewType=='priority' ? 'text-blue-600 font-semibold' : 'text-gray-700' }}">
                    <i class="fas fa-sort-amount-down w-4 {{ $viewType=='priority' ? '' : 'text-gray-400' }}"></i> Priorität
                </a>
            </div>
        </div>
    </div>
</div>
