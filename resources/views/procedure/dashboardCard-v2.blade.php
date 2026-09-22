{{-- Prozessschritte-Card v2 – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
@if(auth()->user()->can('manage procedures') || auth()->user()->can('view assigned procedures'))
    @if($steps && $steps->count() > 0)
        <div class="divide-y divide-gray-100">
            @foreach($steps->sortByDate('endDate', 'desc') as $step)
                <a href="{{ url('procedure/' . $step->procedure->id . '/start') }}"
                   class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 no-underline">
                    <div class="text-center min-w-[3.5rem]">
                        @php
                            $isOverdue = $step->endDate->isPast();
                            $isSoon    = !$isOverdue && $step->endDate->lte(now()->addDays(3));
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $isOverdue ? 'bg-red-100 text-red-700' : ($isSoon ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                            {{ $step->endDate->format('d.m.') }}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-gray-800 font-medium truncate">{{ $step->name }}</div>
                        <div class="text-xs text-gray-500 truncate">{{ $step->procedure->name }}</div>
                    </div>
                    <div class="shrink-0 text-gray-400">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="px-4 py-8 text-center text-gray-400 text-sm">
            <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p>Keine offenen Prozessschritte</p>
        </div>
    @endif
@endif

