@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    {{ $employe->name }} – Änderungsverlauf
@endsection

@section('title')
    Personalverwaltung
@endsection

@section('content')
<div class="personal-wrapper">

    @include('personal.partials._akte_header', ['active' => 'verlauf'])

    <div class="personal-card mb-4 text-sm text-gray-600">
        Das Protokoll wird automatisch geführt und kann nicht bearbeitet werden. Es umfasst Stammdaten, Verträge inkl. Lehrer-Details,
        Dokumente, Qualifikationen, Einwilligungen, Urlaubsanspruch sowie Prozesse und Wiedervorlagen.
        Sensible Werte (Sozialversicherungsnummer, ohne Berechtigung auch Vergütung) werden maskiert.
    </div>

    {{-- Filter --}}
    <form method="GET" class="personal-card mb-6 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
        @foreach($categories as $key => $label)
            @continue($key === 'zugriff')
            <label class="inline-flex items-center gap-1.5">
                <input type="checkbox" name="kategorie[]" value="{{ $key }}" @checked(in_array($key, $selected, true))>
                {{ $label }}
            </label>
        @endforeach
        <label class="inline-flex items-center gap-1.5 text-gray-500">
            <input type="checkbox" name="zugriffe" value="1" @checked($withAccess)>
            Lesezugriffe anzeigen
        </label>
        <button type="submit" class="btn-personal-primary text-xs">Filtern</button>
        @if($selected !== [] || $withAccess)
            <a href="{{ route('personal.personalakte.verlauf', $employe->id) }}" class="text-xs text-gray-500 underline">zurücksetzen</a>
        @endif
    </form>

    @forelse($entries->groupBy(fn ($e) => $e['at']->format('Y-m-d')) as $day => $dayEntries)
        <h2 class="text-sm font-semibold text-gray-500 mt-6 mb-2">{{ \Carbon\Carbon::parse($day)->translatedFormat('l, d. F Y') }}</h2>
        <div class="space-y-2">
            @foreach($dayEntries as $entry)
                <div class="personal-card !py-3 {{ $entry['level'] === 'warning' ? 'border-l-4 border-yellow-400' : '' }} {{ $entry['level'] === 'muted' ? 'opacity-70' : '' }}">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600 mr-2">{{ $entry['category_label'] }}</span>
                            <span class="font-medium text-gray-900">{{ $entry['title'] }}</span>
                            <span class="text-gray-500 text-sm">– {{ $entry['event'] }}</span>
                        </div>
                        <div class="text-xs text-gray-500 whitespace-nowrap">
                            {{ $entry['at']->format('H:i') }} Uhr · {{ $entry['who'] }}
                        </div>
                    </div>
                    @if(!empty($entry['changes']))
                        <table class="mt-2 text-sm w-full">
                            @foreach($entry['changes'] as $change)
                                <tr class="border-t border-gray-50">
                                    <td class="py-1 pr-4 text-gray-500 w-1/4 align-top">{{ $change['label'] }}</td>
                                    <td class="py-1">
                                        @if($change['old'] !== null)
                                            <span class="text-red-600 line-through">{{ $change['old'] }}</span>
                                            <span class="text-gray-400">→</span>
                                        @endif
                                        <span class="text-green-700">{{ $change['new'] ?? '–' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </div>
            @endforeach
        </div>
    @empty
        <p class="text-gray-400 text-sm py-8 text-center">Keine Einträge vorhanden.</p>
    @endforelse
</div>
@endsection
