{{--
    Mit diesem Thema verknüpfte Ergebnisboards (Anker für „Karte → Aufgabe“).
    Erwartet: $theme, $ergebnisboards (Collection<Ergebnisboard> mit karten_count).
--}}
@if(auth()->user()->can('view result boards') && ($ergebnisboards->isNotEmpty() || auth()->user()->can('create', \App\Models\Ergebnisboard::class)))
    <div>
        <div class="flex items-center justify-between gap-2 mb-2">
            <h3 class="thm-section-title">Boards</h3>
            @can('create', \App\Models\Ergebnisboard::class)
                <a href="{{ route('ergebnisboards.create', ['theme' => $theme->id]) }}" class="thm-btn thm-btn-secondary thm-btn-sm" title="Neues Board zu diesem Thema">
                    <i class="fas fa-plus"></i>
                </a>
            @endcan
        </div>
        <ul class="space-y-1">
            @forelse($ergebnisboards as $eb)
                <li>
                    <a href="{{ route('ergebnisboards.show', $eb) }}" class="text-sm text-blue-700 hover:underline">
                        <i class="fas fa-th-large text-gray-400 mr-1"></i>{{ $eb->titel }}
                    </a>
                    <span class="text-xs text-gray-500">· {{ $eb->karten_count }} {{ $eb->karten_count === 1 ? 'Karte' : 'Karten' }}</span>
                </li>
            @empty
                <li class="text-sm text-gray-400 italic">Aus Board-Karten werden Aufgaben dieses Themas.</li>
            @endforelse
        </ul>
    </div>
@endif
