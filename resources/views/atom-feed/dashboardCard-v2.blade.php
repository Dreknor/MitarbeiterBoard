{{-- Atom-Feed-Card v2 – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
@if(empty($atomFeedEntries))
    <div class="px-4 py-8 text-center text-gray-400 text-sm">
        <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <p>Feed konnte nicht geladen werden</p>
    </div>
@else
    <div class="divide-y divide-gray-100">
        @foreach($atomFeedEntries as $entry)
            <div class="flex items-start gap-3 px-4 py-3">
                <div class="flex-1 min-w-0">
                    @if(!empty($entry['link']))
                        <a href="{{ $entry['link'] }}" target="_blank" rel="noopener"
                           class="text-sm text-gray-800 truncate block font-medium no-underline hover:text-blue-600"
                           title="{{ $entry['title'] ?? '' }}">
                            {{ $entry['title'] ?? '(kein Titel)' }}
                        </a>
                    @else
                        <span class="text-sm text-gray-800 truncate block font-medium"
                              title="{{ $entry['title'] ?? '' }}">
                            {{ $entry['title'] ?? '(kein Titel)' }}
                        </span>
                    @endif
                    @if(!empty($entry['summary']))
                        <div class="text-xs text-gray-500 mt-0.5 line-clamp-2">
                            {{ \Illuminate\Support\Str::limit($entry['summary'], 120) }}
                        </div>
                    @endif
                </div>
                @if(!empty($entry['published']))
                    <div class="text-xs text-gray-400 shrink-0 mt-0.5">
                        {{ $entry['published']->format('d.m.Y') }}
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif

{{-- Footer --}}
<div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
    <a href="{{ $atomFeedUrl }}" target="_blank" rel="noopener"
       class="text-sm text-blue-600 hover:text-blue-800 no-underline font-medium">
        <i class="fas fa-rss mr-1"></i>Feed öffnen
    </a>
    <a href="{{ route('employes.self') }}#atom-feed-settings"
       class="text-sm text-gray-500 hover:text-gray-700 no-underline">
        <i class="fas fa-cog"></i> Einstellungen
    </a>
</div>

