@extends('layouts.app')

@section('title')
    Arbeitszeit erfassen
@endsection

@section('site-title')
    Arbeitszeitnachweis
@endsection

@push('css')
    @vite(['resources/css/zeit.css', 'resources/js/zeit.js'])
@endpush

@section('content')
<div class="zeit-wrapper max-w-xl">
    <a href="{{ route('timesheets.show', [$user->id, $day->format('Y-m')]) }}#tag-{{ $day->toDateString() }}" class="text-sm text-blue-600 hover:text-blue-800"><i class="fas fa-arrow-left mr-1"></i>zurück zum Nachweis</a>
    <h1 class="zw-page-title mt-1 mb-4">Arbeitszeit erfassen</h1>

    <section class="zw-card">
        <div class="zw-card-head">
            <h2 class="zw-card-title"><i class="far fa-calendar"></i> {{ $day->locale('de')->isoFormat('dddd, DD.MM.YYYY') }}</h2>
            @if($user->id !== auth()->id())<span class="text-sm text-gray-500">{{ $user->name }}</span>@endif
        </div>

        @if($suggestion && ($day->lt(today()) || $suggestion['end'] <= now()->format('H:i')))
            <div class="zw-card-body border-b border-gray-100">
                <div class="zw-alert zw-alert-info items-center">
                    <i class="far fa-calendar-check"></i>
                    <div class="flex-1">Dienstplan: <strong>{{ $suggestion['start'] }}–{{ $suggestion['end'] }} Uhr</strong>@if($suggestion['pause']) · {{ $suggestion['pause'] }} Min. Pause @endif</div>
                    <form action="{{ route('timesheets.day.plan', [$user->id, $timesheet->id, $day->toDateString()]) }}" method="post">
                        @csrf
                        <button type="submit" class="zw-btn zw-btn-sm zw-btn-primary">Übernehmen</button>
                    </form>
                </div>
            </div>
        @endif

        <form action="{{ route('timesheets.day.store', [$user->id, $timesheet->id, $day->toDateString()]) }}" method="post" class="zw-card-body flex flex-col gap-4">
            @csrf
            @include('personal.timesheets._dayForm', ['maxZeit' => $day->isToday() ? now()->format('H:i') : null, 'werte' => ['start' => $suggestion['start'] ?? '', 'end' => $suggestion['end'] ?? '', 'pause' => $suggestion['pause'] ?? '']])
            <div class="flex justify-end gap-2">
                <a href="{{ route('timesheets.show', [$user->id, $day->format('Y-m')]) }}" class="zw-btn zw-btn-secondary">Abbrechen</a>
                <button type="submit" class="zw-btn zw-btn-primary"><i class="fas fa-save"></i> Speichern</button>
            </div>
        </form>
    </section>
</div>
@endsection
