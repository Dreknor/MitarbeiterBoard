{{-- Nachrichten-Card v2 – nur Body-Inhalt (cardWrapper übernimmt Header) --}}

{{-- Action-Bar mit "Neue Nachricht"-Button (bei Berechtigung) --}}
@can('create posts')
    <div class="px-4 py-2 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">
            <i class="fas fa-newspaper mr-1 opacity-60"></i>
            {{ $posts->count() > 0 ? $posts->count() . ' ' . ($posts->count() === 1 ? 'Nachricht' : 'Nachrichten') : 'Keine Nachrichten' }}
        </span>
        <a href="{{ url('posts/create') }}"
           class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-medium
                  bg-blue-600 text-white hover:bg-blue-700 no-underline">
            <i class="fas fa-plus"></i> Neue Nachricht
        </a>
    </div>
@endcan

<div x-data="{ showAll: false }">
    @if($posts->count() > 0)
        <div class="divide-y divide-gray-100">
            @foreach($posts as $index => $post)
                @if($post->released == 1 || $post->author_id == auth()->id())
                    <div x-show="showAll || {{ $index < 3 ? 'true' : 'false' }}">
                        <a href="{{ url('posts/' . $post->id) }}"
                           class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 no-underline">
                            <div class="shrink-0 w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-xs font-bold">
                                {{ strtoupper(substr($post->author->name ?? 'U', 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-800 truncate">{{ $post->title }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    {{ $post->author->name ?? '' }}
                                    &middot;
                                    {{ $post->created_at->diffForHumans() }}
                                    @if(!$post->released)
                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                            Entwurf
                                        </span>
                                    @endif
                                </div>
                                @if($post->content)
                                    <div class="text-xs text-gray-600 mt-1 line-clamp-2">
                                        {{ \Illuminate\Support\Str::limit(strip_tags($post->content), 100) }}
                                    </div>
                                @endif
                            </div>
                            @if($post->created_at->gt(now()->subDays(1)))
                                <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                    Neu
                                </span>
                            @endif
                        </a>
                    </div>
                @endif
            @endforeach
        </div>

        @if($posts->count() > 3)
            <div class="px-4 py-2 border-t border-gray-100 text-center">
                <button @click="showAll = !showAll"
                        class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                    <span x-text="showAll ? 'Weniger anzeigen ↑' : '{{ $posts->count() - 3 }} weitere anzeigen ↓'"></span>
                </button>
            </div>
        @endif
    @else
        <div class="px-4 py-8 text-center text-gray-400 text-sm">
            <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
            </svg>
            <p>Keine Nachrichten aktiv</p>
            @can('create posts')
                <a href="{{ url('posts/create') }}"
                   class="mt-3 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium
                          bg-blue-600 text-white hover:bg-blue-700 no-underline">
                    <i class="fas fa-plus"></i> Erste Nachricht verfassen
                </a>
            @endcan
        </div>
    @endif
</div>

{{-- Footer: Neue Nachricht erstellen (kein separater Posts-Index vorhanden) --}}
@can('create posts')
    @if($posts->count() > 0)
        <div class="px-4 py-3 border-t border-gray-100">
            <a href="{{ url('posts/create') }}"
               class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium
                      bg-blue-600 text-white hover:bg-blue-700 no-underline">
                <i class="fas fa-plus"></i> Neue Nachricht veröffentlichen
            </a>
        </div>
    @endif
@endcan

