{{-- Wiki-Card v2 – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
@if($favoriten->isNotEmpty())
    <div class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Meine Favoriten</div>
    <div class="divide-y divide-gray-100">
        @foreach($favoriten as $site)
            <a href="{{ $site->url() }}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 no-underline">
                <div class="shrink-0 w-7 h-7 bg-amber-100 text-amber-500 rounded-lg flex items-center justify-center">
                    <i class="fas fa-star text-xs"></i>
                </div>
                <div class="flex-1 min-w-0 text-sm text-gray-800 truncate">{{ $site->titel }}</div>
            </a>
        @endforeach
    </div>
    <div class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Zuletzt geändert</div>
@endif
<div class="divide-y divide-gray-100">
    @forelse($sites as $site)
        <a href="{{ $site->url() }}" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 no-underline">
            <div class="shrink-0 w-7 h-7 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-file-alt text-xs"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm text-gray-800 truncate">{{ $site->titel }}</div>
            </div>
            <div class="text-xs text-gray-500 shrink-0">
                {{ $site->bearbeitet_at?->diffForHumans() }}
            </div>
        </a>
    @empty
        <div class="px-4 py-8 text-center text-gray-400 text-sm">
            <i class="fa fa-book text-2xl mb-2 opacity-40"></i>
            <p>Keine Wiki-Seiten vorhanden</p>
        </div>
    @endforelse
</div>

{{-- Footer --}}
<div class="px-4 py-3 border-t border-gray-100">
    <a href="{{ route('wiki') }}"
       class="flex items-center justify-center gap-1 text-sm text-blue-600 hover:text-blue-800 no-underline font-medium">
        Zum Wiki →
    </a>
</div>
