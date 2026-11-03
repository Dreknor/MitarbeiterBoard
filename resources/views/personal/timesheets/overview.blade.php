@extends('layouts.app')

@section('title')
    Verlauf Arbeitszeitnachweise
@endsection

@section('site-title')
    Arbeitszeitnachweise
@endsection

@push('css')
    @vite(['resources/css/zeit.css', 'resources/js/zeit.js'])
@endpush

@php
    $hm = function ($sekunden) {
        $minuten = (int) round($sekunden / 60);
        return ($minuten < 0 ? '−' : '').intdiv(abs($minuten), 60).':'.str_pad((string) (abs($minuten) % 60), 2, '0', STR_PAD_LEFT);
    };
    $fmt = fn ($wert) => \App\Services\Personal\Zeit\UrlaubskontoService::format((float) $wert);
@endphp

@section('content')
<div class="zeit-wrapper max-w-4xl">
    <a href="{{ route('timesheets.show', $user->id) }}" class="text-sm text-blue-600 hover:text-blue-800"><i class="fas fa-arrow-left mr-1"></i>zum Nachweis</a>
    <h1 class="zw-page-title mt-1 mb-4">Verlauf – {{ $user->name }}</h1>

    <section class="zw-card">
        @if($timesheets->isEmpty())
            <div class="zw-empty"><i class="fas fa-clock"></i> Noch keine Nachweise.</div>
        @else
            <ul class="zw-list">
                @foreach($timesheets as $ts)
                    <li>
                        <a href="{{ route('timesheets.show', [$user->id, $ts->monthStart()->format('Y-m')]) }}" class="zw-row hover:bg-gray-50">
                            <div class="w-28 font-semibold text-gray-900">{{ $ts->monthStart()->locale('de')->isoFormat('MMM YYYY') }}</div>
                            <div class="flex-1 text-sm">
                                <span class="{{ $ts->working_time_account < 0 ? 'text-red-600' : 'text-emerald-700' }} font-semibold">{{ $hm($ts->working_time_account) }} h</span>
                                <span class="text-gray-400 mx-1">·</span>
                                <span class="text-gray-600">Urlaub {{ $fmt($ts->holidays_new) }} · Rest {{ $fmt($ts->holidays_rest) }}</span>
                            </div>
                            <span class="zw-badge {{ ['offen' => 'zw-badge-gray', 'eingereicht' => 'zw-badge-blue', 'abgeschlossen' => 'zw-badge-green'][$ts->status] }}">{{ $ts->status_label }}</span>
                            <i class="fas fa-chevron-right text-gray-300"></i>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
