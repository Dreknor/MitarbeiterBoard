{{--
    Eintrag in einer Nachrichtenliste (Gruppen-Seite, Hinweis auf der Themen-Seite).
    Erwartet $post mit den Attributen mein_status / muss_bestaetigen (PostService::mitStatus()).
--}}
@php
    $eigene = $post->author_id === auth()->id();
    $gelesen = $eigene || $post->mein_status?->gelesen_at !== null;
    $neu = $post->released && ! $gelesen && $post->datum()?->gt(now()->subDays(\App\Models\Post::NEU_TAGE));
    $offen = (bool) $post->muss_bestaetigen;
@endphp
<a href="{{ route('posts.show', $post) }}{{ isset($zurueck) ? '?zurueck='.urlencode($zurueck) : '' }}"
   class="nachr-item {{ $offen ? 'is-offen' : ($neu ? 'is-neu' : '') }}">
    <div class="nachr-avatar" aria-hidden="true">{{ mb_substr($post->author->name ?? '?', 0, 1) }}</div>
    <div class="flex-1 min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="nachr-item-title">{{ $post->header }}</h3>
            @if(! $post->released)
                <span class="nachr-badge nachr-badge-amber"><i class="fas fa-pen"></i> Entwurf</span>
            @endif
            @if($offen)
                <span class="nachr-badge nachr-badge-amber"><i class="fas fa-signature"></i> Bestätigung offen</span>
            @elseif($post->bestaetigung_erforderlich && $post->mein_status?->bestaetigt_at)
                <span class="nachr-badge nachr-badge-green"><i class="fas fa-check"></i> bestätigt</span>
            @elseif($post->bestaetigung_erforderlich)
                <span class="nachr-badge nachr-badge-gray"><i class="fas fa-signature"></i> mit Lesebestätigung</span>
            @endif
            @if($post->getMedia('files')->count() + $post->getMedia('images')->count() > 0)
                <span class="nachr-badge nachr-badge-gray" title="Anhänge"><i class="fas fa-paperclip"></i> {{ $post->getMedia('files')->count() + $post->getMedia('images')->count() }}</span>
            @endif
        </div>
        <div class="nachr-meta mt-0.5">
            <span>{{ $post->author->name }}</span>
            <span aria-hidden="true">·</span>
            <span title="{{ $post->datum()?->format('d.m.Y H:i') }}">{{ $post->datum()?->diffForHumans() }}</span>
            @if($post->groups->count() > 1)
                <span aria-hidden="true">·</span>
                <span>{{ $post->groups->pluck('name')->join(', ') }}</span>
            @endif
            @if($offen && ($frist = $post->mein_status?->frist ?? $post->bestaetigung_frist))
                <span aria-hidden="true">·</span>
                <span class="{{ $frist->isPast() && ! $frist->isToday() ? 'text-red-600 font-semibold' : 'text-amber-700 font-semibold' }}">
                    bis {{ $frist->format('d.m.Y') }}
                </span>
            @endif
        </div>
        @if($post->kurztext !== '')
            <p class="text-sm text-gray-600 mt-1.5 line-clamp-3">{{ $post->kurztext }}</p>
        @endif
    </div>
    <i class="fas fa-chevron-right text-gray-300 mt-3 hidden sm:block" aria-hidden="true"></i>
</a>
