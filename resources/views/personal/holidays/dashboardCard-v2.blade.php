{{-- Urlaub-Card v2 – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
{{-- Zeigt: Zu genehmigende Urlaube (für Vorgesetzte) + eigene kommende Urlaubstage --}}

@can('approve holidays')
    @if($unapproved && count($unapproved) > 0)
        <div class="px-4 py-2 bg-amber-50 border-b border-amber-100">
            <span class="text-xs font-semibold text-amber-700">
                <i class="fas fa-clock mr-1"></i>
                {{ count($unapproved) }} ausstehende {{ count($unapproved) === 1 ? 'Anfrage' : 'Anfragen' }}
            </span>
        </div>
        <div class="divide-y divide-amber-100">
            @foreach($unapproved as $holiday)
                @if($holiday->employe->id != auth()->id())
                    <div class="px-4 py-3 bg-amber-50/40">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <div class="text-sm font-medium text-gray-800">{{ $holiday->employe->name }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ $holiday->start_date->format('d.m.Y') }} – {{ $holiday->end_date->format('d.m.Y') }}
                                    &middot; {{ $holiday->days_label }}
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <form action="{{ route('holidays.approve', $holiday) }}" method="post" class="flex-1">
                                @csrf
                                <button type="submit"
                                        class="w-full px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-medium hover:bg-emerald-700">
                                    <i class="fas fa-check mr-1"></i> Genehmigen
                                </button>
                            </form>
                            <a href="{{ route('holidays.index') }}#entscheiden"
                               class="flex-1 text-center px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-50 no-underline">
                                Ablehnen …
                            </a>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
@endcan

{{-- Eigene kommende Urlaubstage --}}
@if($holidays->count() > 0)
    <div class="px-4 py-2 bg-gray-50 border-b border-gray-100">
        <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
            <i class="fas fa-umbrella-beach mr-1"></i> Mein Urlaub
        </span>
    </div>
    <div class="divide-y divide-gray-100">
        @foreach($holidays as $holiday)
            <div class="flex items-center gap-3 px-4 py-3">
                <div class="text-center min-w-[3.5rem]">
                    <div class="text-sm font-bold text-gray-800">{{ $holiday->start_date->format('d.m.') }}</div>
                    @if($holiday->end_date->gt($holiday->start_date))
                        <div class="text-xs text-gray-500">– {{ $holiday->end_date->format('d.m.') }}</div>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs text-gray-500">
                        {{ $holiday->days_label }}
                    </div>
                </div>
                <div class="shrink-0">
                    @if($holiday->approved)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                            <i class="fas fa-check mr-1"></i> Genehmigt
                        </span>
                    @elseif($holiday->rejected ?? false)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                            <i class="fas fa-times mr-1"></i> Abgelehnt
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                            <i class="fas fa-clock mr-1"></i> Ausstehend
                        </span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@else
    @can('approve holidays')
        @if(!$unapproved || count($unapproved) === 0)
    @endcan
        <div class="px-4 py-8 text-center text-gray-400 text-sm">
            <i class="fas fa-umbrella-beach text-2xl mb-2 block opacity-40"></i>
            Keine kommenden Urlaubstage
        </div>
    @can('approve holidays')
        @endif
    @endcan
@endif

{{-- Footer --}}
<div class="px-4 py-3 border-t border-gray-100">
    <a href="{{ route('holidays.index') }}"
       class="flex items-center justify-center gap-1 text-sm text-blue-600 hover:text-blue-800 no-underline font-medium">
        Urlaub beantragen →
    </a>
</div>

