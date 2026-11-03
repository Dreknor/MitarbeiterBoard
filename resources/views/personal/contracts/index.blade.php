@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    {{ $employe->vorname }} {{ $employe->familienname }} – Verträge
@endsection

@section('title')
    Personalverwaltung
@endsection

@section('content')
<div class="personal-wrapper" x-data="personalTabs(@js(in_array(request()->query('tab'), ['aktiv', 'ruhend', 'beendet', 'lehrer'], true) ? request()->query('tab') : 'aktiv'))">

    @include('personal.partials._akte_header', [
        'active'  => 'vertraege',
        'actions' => auth()->user()->can('createFor', [\App\Models\personal\Employment::class, $employe])
            ? '<a href="' . e(route('personal.contracts.create', $employe->id)) . '" class="btn-personal-primary text-sm">+ Neue Anstellung</a>'
            : '',
    ])

    @foreach((array) session('vertrags_warnungen', []) as $warnung)
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
        <div class="flex items-center gap-2">
            <span class="text-yellow-500 text-lg">⚠️</span>
            <strong class="text-yellow-800">Hinweis zur Vertragsprüfung</strong>
        </div>
        <p class="text-yellow-700 mt-1 text-sm">{{ $warnung }}</p>
    </div>
    @endforeach

    {{-- Tab-Navigation --}}
    <div class="flex border-b border-gray-200 mb-6">
        <button type="button" @click="setTab('aktiv')"
                :class="isActive('aktiv') ? 'personal-tab personal-tab-active' : 'personal-tab personal-tab-inactive'">
            Aktiv ({{ $activeContracts->count() }})
        </button>
        <button type="button" @click="setTab('ruhend')"
                :class="isActive('ruhend') ? 'personal-tab personal-tab-active' : 'personal-tab personal-tab-inactive'">
            Ruhend ({{ $ruhendeContracts->count() }})
        </button>
        <button type="button" @click="setTab('beendet')"
                :class="isActive('beendet') ? 'personal-tab personal-tab-active' : 'personal-tab personal-tab-inactive'">
            Beendet ({{ $pastContracts->count() }})
        </button>
        @if($hasTeacher)
        <button type="button" @click="setTab('lehrer')"
                :class="isActive('lehrer') ? 'personal-tab personal-tab-active' : 'personal-tab personal-tab-inactive'">
            Lehrer-Details
        </button>
        @endif
    </div>

    {{-- Aktive Verträge --}}
    <div x-show="isActive('aktiv')" x-cloak>
        @forelse($activeContracts as $emp)
            @include('personal.contracts._employment_card', ['employment' => $emp])
        @empty
            <p class="text-gray-400 text-sm py-8 text-center">Keine aktiven Anstellungen</p>
        @endforelse
    </div>

    {{-- Ruhende Verträge --}}
    <div x-show="isActive('ruhend')" x-cloak>
        @forelse($ruhendeContracts as $emp)
            @include('personal.contracts._employment_card', ['employment' => $emp])
        @empty
            <p class="text-gray-400 text-sm py-8 text-center">Keine ruhenden Anstellungen</p>
        @endforelse
    </div>

    {{-- Beendete Verträge --}}
    <div x-show="isActive('beendet')" x-cloak>
        @forelse($pastContracts as $emp)
            @include('personal.contracts._employment_card', ['employment' => $emp, 'readonly' => true])
        @empty
            <p class="text-gray-400 text-sm py-8 text-center">Keine beendeten Anstellungen</p>
        @endforelse
    </div>

    {{-- Lehrer-Details --}}
    @if($hasTeacher)
    <div x-show="isActive('lehrer')" x-cloak>
        @foreach($activeContracts->merge($ruhendeContracts)->filter(fn ($e) => $e->employment_type?->requiresTeacherDetail()) as $emp)
        <div class="personal-card mb-4">
            <h3 class="font-semibold text-gray-900 mb-3">
                Lehrer-Details: {{ $emp->department?->name ?? '—' }} (ab {{ $emp->start->format('d.m.Y') }})
            </h3>
            @if($detail = $emp->currentTeacherDetail)
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500">Schulart</dt>
                    <dd class="font-medium">{{ $detail->schoolType?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Deputat</dt>
                    <dd class="font-medium">{{ $detail->deputat_hours }} Std.</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Ermäßigung</dt>
                    <dd class="font-medium">{{ $detail->reduction_hours }} Std.
                        @if($detail->reduction_reason)
                            <span class="text-gray-400">({{ $detail->reduction_reason }})</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Anrechnungsstunden</dt>
                    <dd class="font-medium">{{ $detail->anrechnungsstunden }} Std.</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Effektive Stunden</dt>
                    <dd class="font-medium text-blue-700">{{ number_format($detail->effective_hours, 2) }} Std.</dd>
                </div>
            </dl>
            @else
            <p class="text-gray-400 text-sm">Keine Lehrer-Details hinterlegt</p>
            @endif
        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection

@push('js')
    @vite('resources/js/personal.js')
@endpush

