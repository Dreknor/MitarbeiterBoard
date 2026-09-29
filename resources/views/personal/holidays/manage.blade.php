@extends('layouts.app')

@section('title')
    Urlaubsverwaltung
@endsection

@section('site-title')
    Urlaubsverwaltung
@endsection

@push('css')
    @vite(['resources/css/zeit.css', 'resources/js/zeit.js'])
@endpush

@php
    $fmt = fn ($wert) => \App\Services\Personal\Zeit\UrlaubskontoService::format((float) $wert);
    $statusBadge = ['beantragt' => 'zw-badge-amber', 'genehmigt' => 'zw-badge-green', 'abgelehnt' => 'zw-badge-red', 'storno_beantragt' => 'zw-badge-violet'];
    $query = fn (array $mehr) => request()->fullUrlWithQuery($mehr);
@endphp

@section('content')
<div class="zeit-wrapper">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div class="min-w-0">
            <a href="{{ route('holidays.index') }}" class="text-sm text-blue-600 hover:text-blue-800"><i class="fas fa-arrow-left mr-1"></i>Urlaub</a>
            <h1 class="zw-page-title mt-1">Urlaubsverwaltung {{ $jahr }}</h1>
            <p class="zw-page-sub">{{ $mitarbeitende->count() }} Person(en) in deiner Zuständigkeit</p>
        </div>
        <div class="flex items-center gap-1">
            @can('edit employe')
                <a href="{{ route('personal.vorgesetzte.index') }}" class="zw-btn zw-btn-sm zw-btn-secondary mr-2"><i class="fas fa-sitemap"></i><span class="hidden sm:inline">Vorgesetzte &amp; Stellvertretungen</span></a>
            @endcan
            <a href="{{ $query(['year' => $jahr - 1, 'page' => null]) }}" class="zw-btn-icon" title="Vorjahr"><i class="fas fa-chevron-left"></i></a>
            <span class="font-semibold px-2">{{ $jahr }}</span>
            <a href="{{ $query(['year' => $jahr + 1, 'page' => null]) }}" class="zw-btn-icon" title="Folgejahr"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>

    <nav class="zw-tabs mb-5">
        <a href="{{ $query(['tab' => 'antraege', 'page' => null]) }}" class="zw-tab {{ $tab !== 'konten' ? 'is-active' : '' }}"><i class="fas fa-list"></i> Anträge</a>
        <a href="{{ $query(['tab' => 'konten', 'page' => null]) }}" class="zw-tab {{ $tab === 'konten' ? 'is-active' : '' }}"><i class="fas fa-wallet"></i> Urlaubskonten</a>
    </nav>

    @if($tab === 'konten')
        <section class="zw-card">
            @if($konten->isEmpty())
                <div class="zw-empty"><i class="fas fa-users"></i> Keine Personen in deiner Zuständigkeit.</div>
            @else
                {{-- Desktop: Tabelle --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="zw-table">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th class="text-right">Anspruch</th>
                            <th class="text-right">Übertrag</th>
                            <th class="text-right">Buchungen</th>
                            <th class="text-right">Genehmigt</th>
                            <th class="text-right">Offen</th>
                            <th class="text-right">Verfallen</th>
                            <th class="text-right">Rest</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($konten as $k)
                            <tr>
                                <td class="font-medium text-gray-900">{{ $k['user']->name }}</td>
                                <td class="text-right">{{ $fmt($k['anspruch']) }}</td>
                                <td class="text-right">{{ $fmt($k['uebertrag']) }}</td>
                                <td class="text-right">{{ $fmt($k['buchungen']) }}</td>
                                <td class="text-right">{{ $fmt($k['genommen']) }}</td>
                                <td class="text-right">{{ $fmt($k['beantragt']) }}</td>
                                <td class="text-right">{{ $fmt($k['verfallen']) }}@if($k['verfall_droht'] > 0) <span class="zw-badge zw-badge-amber ml-1" title="droht zu verfallen">{{ $fmt($k['verfall_droht']) }}</span>@endif</td>
                                <td class="text-right font-bold {{ $k['rest'] < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $fmt($k['rest']) }}</td>
                                <td class="text-right"><a href="{{ route('holidays.account', [$k['user']->id, $jahr]) }}" class="zw-btn zw-btn-sm zw-btn-ghost">Details</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- Mobil: Karten --}}
                <ul class="md:hidden zw-list">
                    @foreach($konten as $k)
                        <li>
                            <a href="{{ route('holidays.account', [$k['user']->id, $jahr]) }}" class="zw-row">
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-gray-900 truncate">{{ $k['user']->name }}</div>
                                    <div class="text-xs text-gray-500">Anspruch {{ $fmt($k['anspruch']) }} · Übertrag {{ $fmt($k['uebertrag']) }} · genehmigt {{ $fmt($k['genommen']) }}@if($k['beantragt'] > 0) · offen {{ $fmt($k['beantragt']) }}@endif</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold {{ $k['rest'] < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $fmt($k['rest']) }}</div>
                                    <div class="text-[10px] uppercase text-gray-400">Rest</div>
                                </div>
                                <i class="fas fa-chevron-right text-gray-300"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @else
        <form method="get" class="zw-card mb-4 px-4 py-3 sm:px-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <input type="hidden" name="year" value="{{ $jahr }}">
            <input type="hidden" name="tab" value="antraege">
            <div class="lg:col-span-2">
                <label class="zw-label" for="user_id">Person</label>
                <select name="user_id" id="user_id" class="zw-select">
                    <option value="">Alle</option>
                    @foreach($mitarbeitende as $person)
                        <option value="{{ $person->id }}" @selected((string) $filter['user_id'] === (string) $person->id)>{{ $person->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="zw-label" for="status">Status</label>
                <select name="status" id="status" class="zw-select">
                    @foreach(['alle' => 'Alle', 'offen' => 'Beantragt', 'genehmigt' => 'Genehmigt', 'abgelehnt' => 'Abgelehnt', 'storno' => 'Stornierung beantragt'] as $wert => $text)
                        <option value="{{ $wert }}" @selected($filter['status'] === $wert)>{{ $text }}</option>
                    @endforeach
                </select>
            </div>
            <label class="zw-check">
                <input type="checkbox" name="future_only" value="1" @checked($filter['future_only'])>
                nur laufende/künftige
            </label>
            <button type="submit" class="zw-btn zw-btn-primary"><i class="fas fa-filter"></i> Filtern</button>
        </form>

        <section class="zw-card">
            @if($antraege->isEmpty())
                <div class="zw-empty"><i class="fas fa-search"></i> Keine Anträge für diese Auswahl.</div>
            @else
                <ul class="zw-list">
                    @foreach($antraege as $antrag)
                        <li class="px-4 py-3 sm:px-5 flex flex-wrap items-center gap-x-4 gap-y-2" x-data="{ ablehnen: false }">
                            <div class="flex-1 min-w-[12rem]">
                                <div class="font-semibold text-gray-900">{{ $antrag->employe?->name ?? 'Unbekannt' }}</div>
                                <div class="text-sm text-gray-600">
                                    {{ $antrag->start_date->format('d.m.Y') }}@if(!$antrag->start_date->isSameDay($antrag->end_date)) – {{ $antrag->end_date->format('d.m.Y') }}@endif
                                    · {{ $antrag->days_label }}
                                </div>
                                @if($antrag->approved_by_user && !$antrag->is_pending)
                                    <div class="text-xs text-gray-400">{{ $antrag->rejected ? 'abgelehnt' : 'genehmigt' }} von {{ $antrag->approved_by_user->name }} am {{ $antrag->approved_at?->format('d.m.Y') }}</div>
                                @endif
                                @if($antrag->rejection_reason)<div class="text-xs text-red-600">{{ $antrag->rejection_reason }}</div>@endif
                            </div>
                            <span class="zw-badge {{ $statusBadge[$antrag->status] }}">{{ $antrag->status_label }}</span>
                            <div class="flex flex-wrap items-center gap-1">
                                @if($antrag->is_pending)
                                    @can('approve', $antrag)
                                        <form action="{{ route('holidays.approve', $antrag) }}" method="post">@csrf
                                            <button type="submit" class="zw-btn zw-btn-sm zw-btn-success"><i class="fas fa-check"></i><span class="hidden sm:inline">Genehmigen</span></button>
                                        </form>
                                        <button type="button" class="zw-btn zw-btn-sm zw-btn-secondary" @click="ablehnen = !ablehnen"><i class="fas fa-times"></i><span class="hidden sm:inline">Ablehnen</span></button>
                                    @endcan
                                @endif
                                @can('delete', $antrag)
                                    <form action="{{ route('holidays.destroy', $antrag) }}" method="post" data-confirm="Urlaub von {{ $antrag->employe?->name }} ab {{ $antrag->start_date->format('d.m.Y') }} wirklich entfernen? Abwesenheit und Arbeitszeitnachweis werden angepasst.">
                                        @csrf @method('delete')
                                        <button type="submit" class="zw-btn zw-btn-sm zw-btn-danger-ghost" title="Entfernen"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endcan
                                @if($antrag->employe)
                                    <a href="{{ route('holidays.account', [$antrag->employe_id, $jahr]) }}" class="zw-btn zw-btn-sm zw-btn-ghost" title="Urlaubskonto"><i class="fas fa-wallet"></i></a>
                                @endif
                            </div>
                            @if($antrag->is_pending)
                                <form x-show.important="ablehnen" x-cloak action="{{ route('holidays.reject', $antrag) }}" method="post" class="basis-full flex flex-col sm:flex-row gap-2">
                                    @csrf
                                    <input type="text" name="reason" maxlength="255" class="zw-input" placeholder="Begründung">
                                    <button type="submit" class="zw-btn zw-btn-danger shrink-0">Ablehnen</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <div class="px-4 py-3 border-t border-gray-100">{{ $antraege->links() }}</div>
            @endif
        </section>
    @endif
</div>
@endsection
