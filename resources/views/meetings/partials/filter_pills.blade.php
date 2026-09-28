{{-- Filterleiste für Übersicht & Archiv. Erwartet: $route, $filter, $filterGroups, optional $search --}}
@php
    $pillParams = fn (string $value) => array_filter([
        'filter' => $value === 'all' ? null : $value,
        'q'      => $search ?? null,
    ]);
@endphp
<nav class="mtg-pills mb-5" aria-label="Meetings filtern">
    <a href="{{ route($route, $pillParams('all')) }}" class="mtg-pill {{ $filter === 'all' ? 'is-active' : '' }}">
        <i class="fas fa-layer-group"></i> Alle
    </a>
    <a href="{{ route($route, $pillParams('free')) }}" class="mtg-pill {{ $filter === 'free' ? 'is-active' : '' }}">
        <i class="fas fa-globe"></i> Freie Meetings
    </a>
    <a href="{{ route($route, $pillParams('mine')) }}" class="mtg-pill {{ $filter === 'mine' ? 'is-active' : '' }}">
        <i class="fas fa-crown"></i> Von mir organisiert
    </a>
    @foreach($filterGroups as $g)
        <a href="{{ route($route, $pillParams('group-' . $g->id)) }}" class="mtg-pill {{ $filter === 'group-' . $g->id ? 'is-active' : '' }}">
            <i class="fas fa-users"></i> {{ $g->name }}
        </a>
    @endforeach
</nav>
