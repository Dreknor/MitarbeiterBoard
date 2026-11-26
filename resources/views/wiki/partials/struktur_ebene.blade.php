<ul data-parent="{{ $parentId ?? '' }}">
    @foreach($knoten as $k)
        <li data-id="{{ $k['id'] }}">
            <div class="wiki-struktur-knoten">
                <i class="fas fa-grip-vertical text-gray-300"></i>
                <span class="min-w-0 flex-1 truncate">{{ $k['titel'] }}</span>
                @if($k['eingeschraenkt'])<i class="fas fa-lock text-xs text-amber-500" title="eingeschränkt"></i>@endif
            </div>
            @include('wiki.partials.struktur_ebene', ['knoten' => $k['kinder'], 'parentId' => $k['id']])
        </li>
    @endforeach
</ul>
