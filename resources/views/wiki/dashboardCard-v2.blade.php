{{-- Wiki-Card v2 – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
<div class="divide-y divide-gray-100">
    @forelse($sites as $site)
        <a href="{{ route('wiki', $site->slug) }}"
           class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 no-underline">
            <div class="shrink-0 w-7 h-7 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-file-alt text-xs"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm text-gray-800 truncate">{{ $site->title }}</div>
            </div>
            <div class="text-xs text-gray-500 shrink-0">
                {{ $site->updated_at->diffForHumans() }}
            </div>
        </a>
    @empty
        <div class="px-4 py-8 text-center text-gray-400 text-sm">
            <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
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

