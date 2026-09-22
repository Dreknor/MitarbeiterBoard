@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    {{ $training->title }}
@endsection

@section('content')
<div class="personal-wrapper">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $training->title }}</h1>
            <p class="text-gray-500 text-sm mt-1">
                {{ $training->start_date->format('d.m.Y') }}
                @if($training->start_date->ne($training->end_date)) – {{ $training->end_date->format('d.m.Y') }} @endif
                @if($training->location) · {{ $training->location }} @endif
            </p>
        </div>
        <div class="flex gap-3">
            @can('update', $training)
            <a href="{{ route('personal.trainings.edit', $training->id) }}" class="btn-personal-secondary text-sm">✏️ Bearbeiten</a>
            @endcan
            <a href="{{ route('personal.trainings.index') }}" class="btn-personal-secondary text-sm">← Zurück</a>
        </div>
    </div>

    @if(session('Meldung'))
    <div class="rounded-lg p-4 mb-4 {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' }}">
        {{ session('Meldung') }}
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Details --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="font-semibold text-gray-900 mb-4">Details</h2>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-gray-500">Status</dt><dd class="font-medium">{{ $training->status->label() }}</dd></div>
                    <div><dt class="text-gray-500">Veranstalter</dt><dd>{{ $training->provider ?? '–' }}</dd></div>
                    <div><dt class="text-gray-500">Kosten</dt><dd>{{ $training->cost ? number_format($training->cost, 2, ',', '.') . ' €' : '–' }}</dd></div>
                    <div><dt class="text-gray-500">Max. Teilnehmer</dt><dd>{{ $training->max_participants ?? 'Unbegrenzt' }}</dd></div>
                    @if($training->qualificationType)
                    <div class="col-span-2"><dt class="text-gray-500">Verknüpfte Qualifikation</dt><dd class="font-medium">{{ $training->qualificationType->name }}</dd></div>
                    @endif
                </dl>
                @if($training->description)
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-sm text-gray-700">{!! nl2br(e($training->description)) !!}</p>
                </div>
                @endif
            </div>

            {{-- Meine Anmeldung --}}
            @if($myParticipation)
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <p class="font-medium text-blue-800">Meine Anmeldung: {{ $myParticipation->status->label() }}</p>
                @if($myParticipation->status->value === 'angemeldet' || $myParticipation->status->value === 'bestaetigt')
                <form action="{{ route('personal.trainings.cancel', $training->id) }}" method="POST" class="mt-2">
                    @csrf
                    <button type="submit" class="text-sm text-blue-600 hover:text-blue-800 underline"
                            onclick="return confirm('Anmeldung zurückziehen?')">Abmelden</button>
                </form>
                @endif
            </div>
            @else
            @can('register', $training)
            @if(!$training->isFull())
            <form action="{{ route('personal.trainings.register', $training->id) }}" method="POST">
                @csrf
                <button type="submit" class="btn-personal-primary w-full">✅ Für diese Fortbildung anmelden</button>
            </form>
            @else
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center text-red-700 font-medium">
                Diese Fortbildung ist ausgebucht
            </div>
            @endif
            @endcan
            @endif
        </div>

        {{-- Teilnehmerliste --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="font-semibold text-gray-900 mb-4">
                Teilnehmer ({{ $training->participants->count() }}
                @if($training->max_participants) / {{ $training->max_participants }} @endif)
            </h2>
            @forelse($training->participants as $participant)
            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                <div>
                    <p class="text-sm font-medium text-gray-900">{{ $participant->employe?->name }}</p>
                    <span class="text-xs text-gray-500">{{ $participant->status->label() }}</span>
                </div>
                <div class="flex gap-2">
                    @can('approve trainings')
                    @if($participant->status->value === 'angemeldet')
                    <form action="{{ route('personal.trainings.approve', [$training->id, $participant->employe_id]) }}" method="POST">
                        @csrf
                        <button type="submit" class="text-xs text-green-600 hover:text-green-800">✓ Best.</button>
                    </form>
                    @endif
                    @endcan
                    @can('manage trainings')
                    @if($participant->status->value === 'bestaetigt')
                    <form action="{{ route('personal.trainings.complete', [$training->id, $participant->employe_id]) }}" method="POST">
                        @csrf
                        <button type="submit" class="text-xs text-blue-600 hover:text-blue-800">✓ Teilgenommen</button>
                    </form>
                    @endif
                    @endcan
                </div>
            </div>
            @empty
            <p class="text-sm text-gray-500">Noch keine Anmeldungen</p>
            @endforelse
        </div>
    </div>

</div>
@endsection

