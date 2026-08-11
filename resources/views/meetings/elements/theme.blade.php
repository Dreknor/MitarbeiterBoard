@php
    $hatHeutigesProtokoll = $theme->protocols->where('created_at', '>', \Carbon\Carbon::now()->startOfDay())->count() > 0;
    $eigenePrio = $theme->priorities->where('creator_id', auth()->id())->first();
@endphp
<li id="{{ $theme->id }}" data-priority="{{ $theme->priority }}" class="mtg-theme-row {{ $hatHeutigesProtokoll ? 'is-done' : '' }}">

    {{-- Ersteller --}}
    <div class="flex items-center gap-2 sm:w-40 shrink-0">
        <span class="mtg-avatar" title="{{ $theme->ersteller->name }}">
            @if($theme->ersteller->getMedia('profile')->count() != 0)
                <img src="{{ $theme->ersteller->photo() }}" alt="{{ $theme->ersteller->name }}">
            @else
                {{ \Illuminate\Support\Str::of($theme->ersteller->name)->explode(' ')->map(fn($p) => \Illuminate\Support\Str::substr($p, 0, 1))->take(2)->implode('') }}
            @endif
        </span>
        <span class="text-xs text-gray-500 truncate">{{ $theme->ersteller->name }}</span>
    </div>

    {{-- Titel --}}
    <div class="flex-1 min-w-0">
        <span class="font-semibold text-gray-900 break-words">{{ $theme->theme }}</span>
        @if($hatHeutigesProtokoll)
            <span class="mtg-badge mtg-badge-green ml-1"><i class="fas fa-check"></i> protokolliert</span>
        @endif
    </div>

    {{-- Priorität --}}
    <div class="sm:w-44 shrink-0" id="priority_{{ $theme->id }}">
        @if($eigenePrio)
            <div class="mtg-progress"><span style="width: {{ $theme->priority }}%"></span></div>
        @else
            <input type="range" class="w-full cursor-pointer accent-blue-600" id="theme_{{ $theme->id }}"
                   min="1" max="100" value="0" data-theme="{{ $theme->id }}" data-creatorid="{{ auth()->id() }}"
                   title="Priorität festlegen">
        @endif
    </div>

    {{-- Aktionen --}}
    <div class="flex items-center gap-1.5 shrink-0">
        <a href="{{ url(request()->segment(1).'/themes/'.$theme->id) }}" class="mtg-btn mtg-btn-secondary mtg-btn-sm">
            <i class="far fa-eye"></i> <span class="hidden sm:inline">zeigen</span>
        </a>
        @isset($meeting)
            <form action="{{ route('meetings.themes.remove', ['group' => $group->name, 'meeting' => $meeting->id, 'theme' => $theme->id]) }}"
                  method="POST"
                  onsubmit="return confirm('Thema von diesem Meeting entfernen? Das Thema selbst bleibt erhalten.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="mtg-btn-icon w-8 h-8 text-red-500 hover:bg-red-50" title="Vom Meeting entfernen">
                    <i class="fas fa-unlink"></i>
                </button>
            </form>
        @endisset
    </div>
</li>
