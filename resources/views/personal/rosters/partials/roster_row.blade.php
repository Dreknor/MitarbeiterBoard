{{-- Zeile eines Dienstplans in der Übersicht --}}
@php
    $wochenEnde = $roster->start_date->copy()->endOfWeek();
    $laufend = now()->between($roster->start_date->copy()->startOfDay(), $wochenEnde);
@endphp
<li class="zw-row flex-wrap {{ $laufend ? 'bg-blue-50/50' : '' }}">
    <a href="{{ route('roster.show', $roster->id) }}" class="flex-1 min-w-[12rem]">
        <div class="font-semibold text-gray-900">
            @if($roster->is_template)
                Vorlage vom {{ $roster->start_date->format('d.m.Y') }}
            @else
                KW {{ $roster->start_date->isoWeek() }} · {{ $roster->start_date->format('d.m.') }}–{{ $wochenEnde->format('d.m.Y') }}
            @endif
            @if($laufend)<span class="zw-badge zw-badge-blue ml-1">diese Woche</span>@endif
        </div>
        @if($roster->comment)<div class="text-xs text-gray-500 truncate">{{ $roster->comment }}</div>@endif
    </a>
    <div class="flex flex-wrap items-center gap-1.5">
        @unless($roster->is_template)
            @if($roster->published)
                <span class="zw-badge zw-badge-green"><i class="fas fa-check"></i> veröffentlicht</span>
                @if(($roster->offene_aenderungen ?? 0) > 0)
                    <span class="zw-badge zw-badge-amber" title="Änderungen nach Veröffentlichung, noch nicht mitgeteilt">{{ $roster->offene_aenderungen }} Änderung(en)</span>
                @endif
            @else
                <span class="zw-badge zw-badge-gray">Entwurf</span>
            @endif
        @endunless
    </div>
    <div class="flex items-center gap-1">
        <a href="{{ route('roster.show', $roster->id) }}" class="zw-btn zw-btn-sm zw-btn-secondary"><i class="fas fa-edit"></i><span class="hidden sm:inline">Bearbeiten</span></a>
        @unless($roster->is_template)
            <a href="{{ route('roster.export.pdf', $roster->id) }}" class="zw-btn-icon is-sm" title="PDF" target="_blank"><i class="fas fa-file-pdf"></i></a>
            @if(!$roster->published)
                <form action="{{ route('roster.publish', $roster->id) }}" method="post" data-confirm="Plan veröffentlichen und eingeplante Personen benachrichtigen?">
                    @csrf
                    <button type="submit" class="zw-btn-icon is-sm text-emerald-600" title="Veröffentlichen"><i class="fas fa-bullhorn"></i></button>
                </form>
            @endif
        @endunless
        @if(!$roster->published || !$wochenEnde->isPast())
            <form action="{{ route('roster.delete', $roster->id) }}" method="post" data-confirm="{{ $roster->is_template ? 'Vorlage' : 'Dienstplan' }} löschen?">
                @csrf @method('delete')
                <button type="submit" class="zw-btn-icon is-sm text-red-600" title="Löschen"><i class="fas fa-trash"></i></button>
            </form>
        @endif
    </div>
</li>
