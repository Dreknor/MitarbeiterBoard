{{--
    Mit der Sitzung oder einem Thema verknüpfte Ergebnisboards (nur sichtbare).
    Erwartet: $ergebnisboards (Collection<Ergebnisboard>), optional $neuParameter (Query für „Neues Board“).
--}}
@if(auth()->user()->can('view result boards') && ($ergebnisboards->isNotEmpty() || auth()->user()->can('create', \App\Models\Ergebnisboard::class)))
    <section class="mtg-card">
        <div class="mtg-card-head">
            <h2 class="mtg-card-title"><i class="fas fa-th-large"></i> Ergebnisboards</h2>
            @can('create', \App\Models\Ergebnisboard::class)
                <a href="{{ route('ergebnisboards.create', $neuParameter ?? []) }}" class="mtg-btn mtg-btn-ghost mtg-btn-sm" title="Neues Board für diese Sitzung">
                    <i class="fas fa-plus"></i>
                </a>
            @endcan
        </div>
        <div class="p-5">
            @forelse($ergebnisboards as $eb)
                <a href="{{ route('ergebnisboards.show', $eb) }}" class="flex items-center gap-3 py-2 no-underline hover:no-underline text-gray-800 hover:text-blue-700">
                    <i class="{{ \App\Support\ErgebnisboardVorlagen::finden($eb->vorlage)['icon'] ?? 'fas fa-th' }} text-gray-400 w-4 text-center"></i>
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-semibold truncate">{{ $eb->titel }}</span>
                        <span class="block text-xs text-gray-500">{{ $eb->vorlageTitel() }} · {{ $eb->karten_count }} {{ $eb->karten_count === 1 ? 'Karte' : 'Karten' }}@if($eb->istArchiviert()) · archiviert @endif</span>
                    </span>
                </a>
            @empty
                <p class="mtg-hint m-0">Noch kein Board – z. B. zur Ergebnissicherung als Matrix oder Kanban.</p>
            @endforelse
        </div>
    </section>
@endif
