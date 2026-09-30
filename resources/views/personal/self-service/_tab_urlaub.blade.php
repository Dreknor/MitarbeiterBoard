@php
    $fmt = fn ($wert) => \App\Services\Personal\Zeit\UrlaubskontoService::format((float) $wert);
@endphp

<div class="flex items-center justify-between gap-3 mb-4">
    <div class="flex items-center gap-1">
        <a href="{{ route('self-service.index', ['jahr' => $jahr - 1]) }}#urlaub" class="btn-personal-secondary text-sm" title="Vorjahr">‹</a>
        <span class="font-semibold px-2">{{ $jahr }}</span>
        <a href="{{ route('self-service.index', ['jahr' => $jahr + 1]) }}#urlaub" class="btn-personal-secondary text-sm" title="Folgejahr">›</a>
    </div>
    @can('has holidays')
        <a href="{{ route('holidays.index') }}" class="btn-personal-primary text-sm">Urlaub beantragen</a>
    @endcan
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    {{-- Urlaubskonto --}}
    @if($konto)
    <div class="personal-card lg:col-span-1">
        <h3 class="font-semibold text-gray-900 mb-3">Urlaubskonto {{ $jahr }}</h3>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">Anspruch {{ $jahr }}</dt><dd class="font-medium">{{ $fmt($konto['anspruch']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">+ Übertrag aus {{ $jahr - 1 }}</dt><dd class="font-medium">{{ $fmt($konto['uebertrag']) }}</dd></div>
            @if($konto['buchungen'] != 0)
                <div class="flex justify-between"><dt class="text-gray-500">+ Buchungen</dt><dd class="font-medium">{{ $fmt($konto['buchungen']) }}</dd></div>
            @endif
            <div class="flex justify-between"><dt class="text-gray-500">− genehmigter Urlaub</dt><dd class="font-medium">{{ $fmt($konto['genommen']) }}</dd></div>
            @if($konto['verfallen'] > 0)
                <div class="flex justify-between"><dt class="text-gray-500">− verfallen ({{ $konto['verfallsdatum']?->format('d.m.') }})</dt><dd class="font-medium">{{ $fmt($konto['verfallen']) }}</dd></div>
            @endif
            <div class="flex justify-between border-t border-gray-200 pt-2 text-base">
                <dt class="font-semibold">Resturlaub</dt>
                <dd class="font-bold {{ $konto['rest'] < 0 ? 'text-red-600' : 'text-green-700' }}">{{ $fmt($konto['rest']) }}</dd>
            </div>
            @if($konto['beantragt'] > 0)
                <div class="flex justify-between text-gray-500"><dt>offen beantragt</dt><dd>{{ $fmt($konto['beantragt']) }}</dd></div>
            @endif
        </dl>
        @if($konto['verfall_droht'] > 0)
            <p class="rounded-lg p-3 mt-3 text-sm bg-yellow-50 text-yellow-800 border border-yellow-200">
                {{ $fmt($konto['verfall_droht']) }} Tag(e) Übertrag verfallen am {{ $konto['verfallsdatum']->format('d.m.Y') }}.
            </p>
        @endif
        <a href="{{ route('holidays.account', [auth()->id(), $jahr]) }}" class="text-sm text-blue-600 hover:underline mt-3 inline-block">Urlaubskonto im Detail →</a>
    </div>
    @endif

    {{-- Urlaub & Abwesenheiten --}}
    <div class="personal-card {{ $konto ? 'lg:col-span-2' : 'lg:col-span-3' }}">
        <h3 class="font-semibold text-gray-900 mb-3">Urlaub & Abwesenheiten {{ $jahr }}</h3>
        @if($abwesenheiten->isEmpty())
            <p class="text-gray-400 text-sm py-6 text-center">Keine Einträge in {{ $jahr }}.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($abwesenheiten as $eintrag)
                    <li class="flex items-center gap-3 py-2 {{ $eintrag['blass'] ? 'opacity-60' : '' }}">
                        <span class="{{ $eintrag['art'] === 'urlaub' ? 'badge-blue' : 'badge-gray' }} shrink-0">
                            {{ $eintrag['art'] === 'urlaub' ? 'Urlaub' : 'Abwesenheit' }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900">
                                {{ $eintrag['start']->format('d.m.Y') }}@if(!$eintrag['start']->isSameDay($eintrag['ende'])) – {{ $eintrag['ende']->format('d.m.Y') }}@endif
                            </div>
                            <div class="text-xs text-gray-500 truncate">
                                @if($eintrag['art'] === 'abwesenheit'){{ $eintrag['titel'] }} · @endif{{ $eintrag['info'] }}
                            </div>
                        </div>
                        @if($eintrag['status'])
                            <span class="{{ $eintrag['badge'] }} shrink-0">{{ $eintrag['status'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

</div>
