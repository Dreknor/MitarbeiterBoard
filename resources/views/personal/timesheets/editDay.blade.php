@extends('layouts.app')

@section('title')
    Arbeitszeit bearbeiten
@endsection

@section('site-title')
    Arbeitszeitnachweis
@endsection

@push('css')
    @vite(['resources/css/zeit.css', 'resources/js/zeit.js'])
@endpush

@section('content')
<div class="zeit-wrapper max-w-xl">
    <a href="{{ route('timesheets.show', [$timesheet->employe_id, $day->format('Y-m')]) }}#tag-{{ $day->toDateString() }}" class="text-sm text-blue-600 hover:text-blue-800"><i class="fas fa-arrow-left mr-1"></i>zurück zum Nachweis</a>
    <h1 class="zw-page-title mt-1 mb-4">Arbeitszeit bearbeiten</h1>

    <section class="zw-card">
        <div class="zw-card-head">
            <h2 class="zw-card-title"><i class="far fa-calendar"></i> {{ $day->locale('de')->isoFormat('dddd, DD.MM.YYYY') }}</h2>
            @if($timesheet_day->source)<span class="zw-badge zw-badge-gray">{{ ['terminal' => 'Terminal', 'dienstplan' => 'aus Dienstplan'][$timesheet_day->source] ?? $timesheet_day->source }}</span>@endif
        </div>
        <form action="{{ route('timesheets.day.update', $timesheet_day) }}" method="post" class="zw-card-body flex flex-col gap-4">
            @csrf
            @method('put')
            @include('personal.timesheets._dayForm', ['maxZeit' => $day->isToday() ? now()->format('H:i') : null, 'werte' => [
                'start' => $timesheet_day->start?->format('H:i'),
                'end' => $timesheet_day->end?->format('H:i'),
                'pause' => $timesheet_day->pause,
                'comment' => $timesheet_day->comment,
            ]])
            <div class="flex flex-wrap justify-between gap-2">
                <button type="submit" form="loeschen" class="zw-btn zw-btn-danger-ghost"><i class="fas fa-trash"></i> Löschen</button>
                <div class="flex gap-2">
                    <a href="{{ route('timesheets.show', [$timesheet->employe_id, $day->format('Y-m')]) }}" class="zw-btn zw-btn-secondary">Abbrechen</a>
                    <button type="submit" class="zw-btn zw-btn-primary"><i class="fas fa-save"></i> Speichern</button>
                </div>
            </div>
        </form>
        <form id="loeschen" action="{{ route('timesheets.day.destroy', $timesheet_day) }}" method="post" data-confirm="Buchung wirklich löschen?">
            @csrf
            @method('delete')
        </form>
    </section>
</div>
@endsection
