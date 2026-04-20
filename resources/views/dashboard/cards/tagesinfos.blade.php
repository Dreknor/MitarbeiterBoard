{{-- Tagesinfos-Card – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
<div class="divide-y divide-gray-100">
    @forelse($tagesinfos as $news)
        <div class="flex items-start gap-3 px-4 py-3">
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
        </div>
    @empty
        <div class="px-4 py-8 text-center text-gray-400 text-sm">
            <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
            </svg>
            <p>Keine aktuellen Tagesinfos</p>
        </div>
    @endforelse
</div>

