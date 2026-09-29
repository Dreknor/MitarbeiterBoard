@extends('layouts.app')

@section('title')
    Urlaubskonto {{ $employe->name }}
@endsection

@section('site-title')
    Urlaubskonto
@endsection

@push('css')
    @vite(['resources/css/zeit.css', 'resources/js/zeit.js'])
@endpush

@php
    $fmt = fn ($wert) => \App\Services\Personal\Zeit\UrlaubskontoService::format((float) $wert);
    $eigenes = $employe->id === auth()->id();
@endphp

@section('content')
<div class="zeit-wrapper">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div class="min-w-0">
            <a href="{{ $eigenes ? route('holidays.index') : route('holidays.manage', ['tab' => 'konten', 'year' => $jahr]) }}" class="text-sm text-blue-600 hover:text-blue-800"><i class="fas fa-arrow-left mr-1"></i>zurück</a>
            <h1 class="zw-page-title mt-1">Urlaubskonto {{ $eigenes ? '' : $employe->name }} {{ $jahr }}</h1>
        </div>
        <div class="flex items-center gap-1">
            <a href="{{ route('holidays.account', [$employe->id, $jahr - 1]) }}" class="zw-btn-icon" title="Vorjahr"><i class="fas fa-chevron-left"></i></a>
            <span class="font-semibold px-2">{{ $jahr }}</span>
            <a href="{{ route('holidays.account', [$employe->id, $jahr + 1]) }}" class="zw-btn-icon" title="Folgejahr"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Berechnung --}}
        <section class="zw-card lg:col-span-1">
            <div class="zw-card-head"><h2 class="zw-card-title"><i class="fas fa-calculator"></i> Berechnung</h2></div>
            <dl class="zw-card-body flex flex-col gap-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-600">Anspruch {{ $jahr }}</dt><dd class="font-semibold">{{ $fmt($konto['anspruch']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-600">+ Übertrag aus {{ $jahr - 1 }}</dt><dd class="font-semibold">{{ $fmt($konto['uebertrag']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-600">+ Buchungen</dt><dd class="font-semibold">{{ $fmt($konto['buchungen']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-600">− genehmigter Urlaub</dt><dd class="font-semibold">{{ $fmt($konto['genommen']) }}</dd></div>
                @if($konto['verfallen'] > 0)
                    <div class="flex justify-between"><dt class="text-gray-600">− verfallen ({{ $konto['verfallsdatum']?->format('d.m.') }})</dt><dd class="font-semibold">{{ $fmt($konto['verfallen']) }}</dd></div>
                @endif
                <div class="flex justify-between border-t border-gray-200 pt-2 text-base"><dt class="font-semibold">Resturlaub</dt><dd class="font-bold {{ $konto['rest'] < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $fmt($konto['rest']) }}</dd></div>
                @if($konto['beantragt'] > 0)
                    <div class="flex justify-between text-gray-500"><dt>offen beantragt</dt><dd>{{ $fmt($konto['beantragt']) }}</dd></div>
                @endif
                @if($konto['verfall_droht'] > 0)
                    <p class="zw-alert zw-alert-warning mt-2">{{ $fmt($konto['verfall_droht']) }} Tag(e) Übertrag verfallen am {{ $konto['verfallsdatum']->format('d.m.Y') }}.</p>
                @endif
                <p class="zw-hint mt-2">Urlaubstage zählen nur an Arbeitstagen laut Vertrag; Feiertage werden nicht angerechnet.</p>
            </dl>
        </section>

        {{-- Anträge --}}
        <section class="zw-card lg:col-span-2">
            <div class="zw-card-head"><h2 class="zw-card-title"><i class="fas fa-list"></i> Urlaub {{ $jahr }}</h2></div>
            @if($antraege->isEmpty())
                <div class="zw-empty"><i class="fas fa-umbrella-beach"></i> Keine Einträge.</div>
            @else
                <ul class="zw-list">
                    @foreach($antraege as $antrag)
                        <li class="zw-row {{ $antrag->trashed() ? 'opacity-60' : '' }}">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $antrag->start_date->format('d.m.Y') }}@if(!$antrag->start_date->isSameDay($antrag->end_date)) – {{ $antrag->end_date->format('d.m.Y') }}@endif
                                </div>
                                <div class="text-xs text-gray-500">{{ $antrag->days_label }}@if($antrag->comment) · {{ $antrag->comment }}@endif</div>
                            </div>
                            <span class="zw-badge {{ $antrag->trashed() ? 'zw-badge-gray' : ['beantragt' => 'zw-badge-amber', 'genehmigt' => 'zw-badge-green', 'abgelehnt' => 'zw-badge-red', 'storno_beantragt' => 'zw-badge-violet'][$antrag->status] }}">
                                {{ $antrag->trashed() ? 'Storniert' : $antrag->status_label }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Buchungen --}}
        <section class="zw-card lg:col-span-3">
            <div class="zw-card-head">
                <h2 class="zw-card-title"><i class="fas fa-pen"></i> Buchungen {{ $jahr }}</h2>
                <span class="text-xs text-gray-500">Korrekturen, Sonderurlaub oder manueller Übertrag</span>
            </div>
            @if($darfBuchen)
                <form action="{{ route('holidays.account.entries.store', $employe->id) }}" method="post" class="zw-card-body grid grid-cols-1 sm:grid-cols-6 gap-3 items-end border-b border-gray-100">
                    @csrf
                    <input type="hidden" name="year" value="{{ $jahr }}">
                    <div class="sm:col-span-1">
                        <label class="zw-label" for="days">Tage (±)</label>
                        <input type="number" step="0.5" name="days" id="days" class="zw-input" required placeholder="z. B. 2 oder -1">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="zw-label" for="type">Art</label>
                        <select name="type" id="type" class="zw-select">
                            @foreach(\App\Models\personal\HolidayAccountEntry::TYPES as $wert => $text)
                                <option value="{{ $wert }}">{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="zw-label" for="reason">Begründung</label>
                        <input type="text" name="reason" id="reason" maxlength="255" class="zw-input" required>
                    </div>
                    <button type="submit" class="zw-btn zw-btn-primary sm:col-span-1"><i class="fas fa-plus"></i> Buchen</button>
                </form>
            @endif
            @if($buchungen->isEmpty())
                <div class="zw-empty"><i class="fas fa-receipt"></i> Keine Buchungen.</div>
            @else
                <ul class="zw-list">
                    @foreach($buchungen as $buchung)
                        <li class="zw-row">
                            <div class="w-16 text-right font-bold {{ $buchung->days < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $buchung->days > 0 ? '+' : '' }}{{ $fmt($buchung->days) }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm text-gray-900">{{ $buchung->type_label }}: {{ $buchung->reason }}</div>
                                <div class="text-xs text-gray-500">{{ $buchung->creator?->name ?? 'System' }} · {{ $buchung->created_at->format('d.m.Y') }}</div>
                            </div>
                            @if($darfBuchen)
                                <form action="{{ route('holidays.account.entries.destroy', $buchung) }}" method="post" data-confirm="Buchung löschen?">
                                    @csrf @method('delete')
                                    <button type="submit" class="zw-btn-icon is-sm text-red-600" title="Löschen"><i class="fas fa-trash"></i></button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>
@endsection
