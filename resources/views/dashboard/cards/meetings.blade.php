@if($naechsteMeetings->isEmpty())
    <div data-card-empty="true" class="px-4 py-8 text-center text-gray-400 text-sm">
        <i class="fas fa-users text-2xl mb-2 block opacity-40"></i>
        Keine bevorstehenden Meetings
    </div>
@else
    <div class="divide-y divide-gray-100">
        @foreach($naechsteMeetings as $meeting)
            <a href="{{ route('meetings.index', ['group' => $meeting->group->name]) }}"
               class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 no-underline">
                <div class="text-center min-w-[3rem]">
                    <div class="text-sm font-bold text-gray-800">{{ $meeting->date->format('d.m.') }}</div>
                    @if($meeting->start_time)
                        <div class="text-xs text-gray-500">
                            {{ \Carbon\Carbon::parse($meeting->start_time)->format('H:i') }}
                        </div>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm text-gray-800 font-medium truncate">
                        {{ $meeting->title ?? $meeting->group->name }}
                    </div>
                    <div class="text-xs text-gray-500 truncate">
                        <i class="fas fa-users mr-1"></i>{{ $meeting->group->name }}
                        @if($meeting->themes_count ?? 0 > 0)
                            &middot; {{ $meeting->themes_count }} Themen
                        @endif
                    </div>
                </div>
                @if($meeting->cancelled)
                    <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                        Abgesagt
                    </span>
                @elseif($meeting->date->isToday())
                    <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                        Heute
                    </span>
                @endif
            </a>
        @endforeach
    </div>
@endif

