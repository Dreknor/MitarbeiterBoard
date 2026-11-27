{{-- Seitenbaum (rekursiv). $knoten: Array aus WikiService::baum(), $wikiAktivId / $wikiOffen aus dem Layout-Composer. --}}
<ul>
    @foreach($knoten as $k)
        @php $hatKinder = ! empty($k['kinder']); @endphp
        <li @if($hatKinder) x-data="wikiKnoten({{ $k['id'] }}, {{ isset($wikiOffen[$k['id']]) ? 'true' : 'false' }})" @endif>
            <div class="wiki-knoten">
                @if($hatKinder)
                    <button type="button" class="wiki-knoten-pfeil" :class="{ offen }" @click="umschalten()" :aria-expanded="offen" aria-label="Unterseiten von {{ $k['titel'] }}">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                @else
                    <span class="wiki-knoten-platz"></span>
                @endif
                <a href="{{ $k['url'] }}" class="{{ $wikiAktivId === $k['id'] ? 'is-aktiv' : '' }}" title="{{ $k['titel'] }}" @if($wikiAktivId === $k['id']) aria-current="page" @endif>
                    @if($k['eingeschraenkt'])<i class="fas fa-lock wiki-symbol" title="Eingeschränkt"></i>@endif{{ $k['titel'] }}
                </a>
            </div>
            @if($hatKinder)
                <div x-show="offen" @if(! isset($wikiOffen[$k['id']])) x-cloak @endif>
                    @include('wiki.partials.baum', ['knoten' => $k['kinder'], 'tiefe' => $tiefe + 1])
                </div>
            @endif
        </li>
    @endforeach
</ul>
