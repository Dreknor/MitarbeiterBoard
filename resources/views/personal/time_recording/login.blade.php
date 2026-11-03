@extends('personal.time_recording.layout')

@php
    $kommen = $aktion === \App\Services\Personal\Zeit\TimeRecordingService::KOMMEN;
    $minuten = (int) round($timesheet->working_time_account / 60);
    $saldo = ($minuten < 0 ? '−' : '+').intdiv(abs($minuten), 60).':'.str_pad((string) (abs($minuten) % 60), 2, '0', STR_PAD_LEFT);
@endphp

@section('content')
    <div class="rounded-3xl px-6 py-10 text-center" style="background: {{ $kommen ? 'rgba(16,185,129,.35)' : 'rgba(59,130,246,.35)' }};">
        <i class="fas {{ $kommen ? 'fa-sign-in-alt' : 'fa-sign-out-alt' }}" style="font-size: 3.5rem;"></i>
        <h1 class="text-3xl font-bold mt-4">{{ $kommen ? 'Hallo' : 'Tschüss' }}, {{ $user->vorname ?: $user->name }}!</h1>
        <p class="text-xl mt-3">
            @if($kommen)
                Kommen um <strong>{{ $timesheet_day->start->format('H:i') }} Uhr</strong> erfasst.
            @else
                Gehen um <strong>{{ $timesheet_day->end->format('H:i') }} Uhr</strong> erfasst.
            @endif
        </p>
        @unless($kommen)
            <p class="text-white/80 mt-1">
                Anwesenheit {{ $timesheet_day->start->diff($timesheet_day->end)->format('%H:%I') }} h
                @if($timesheet_day->pause) · Pause {{ $timesheet_day->pause }} Min. automatisch eingetragen @endif
            </p>
        @endunless
        <p class="mt-6 text-white/80">Stundenkonto aktuell</p>
        <p class="text-3xl font-bold tabular-nums">{{ $saldo }} h</p>

        @if($dayBefore)
            <div class="mt-6 rounded-2xl bg-amber-500/90 px-4 py-3 text-base">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Am {{ $dayBefore->date->format('d.m.Y') }} fehlt das Gehen – bitte im Arbeitszeitnachweis nachtragen.
            </div>
        @endif

        <div class="zw-timer mt-8"><span style="animation-duration: 8s;"></span></div>
        <a href="{{ route('time_recording.logout') }}" class="inline-block mt-6 rounded-2xl bg-white/20 px-6 py-3 text-lg font-semibold">Fertig</a>
    </div>
@endsection

@push('js')
    <script>setTimeout(() => { window.location.href = @json(route('time_recording.logout')); }, 8000);</script>
@endpush
