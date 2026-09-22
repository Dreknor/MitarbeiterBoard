@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    Fortbildungskatalog
@endsection

@section('content')
<div class="personal-wrapper">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Fortbildungskatalog</h1>
        @can('manage trainings')
        <a href="{{ route('personal.trainings.create') }}" class="btn-personal-primary">
            + Fortbildung anlegen
        </a>
        @endcan
    </div>

    @if(session('Meldung'))
    <div class="rounded-lg p-4 mb-4 {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : (session('type') === 'warning' ? 'bg-yellow-50 text-yellow-800 border border-yellow-200' : (session('type') === 'danger' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-blue-50 text-blue-800 border border-blue-200')) }}">
        {{ session('Meldung') }}
    </div>
    @endif

    @forelse($trainings as $training)
    <div class="bg-white rounded-xl border border-gray-200 mb-4">
        <div class="px-6 py-5">
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <a href="{{ route('personal.trainings.show', $training->id) }}" class="hover:text-blue-600">
                                {{ $training->title }}
                            </a>
                        </h3>
                        <span class="text-xs px-2 py-0.5 rounded-full
                            {{ $training->status->value === 'geplant' ? 'bg-blue-100 text-blue-700' :
                               ($training->status->value === 'bestaetigt' ? 'bg-green-100 text-green-700' :
                               ($training->status->value === 'durchgefuehrt' ? 'bg-gray-100 text-gray-700' : 'bg-red-100 text-red-700')) }}">
                            {{ $training->status->label() }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-500 mt-2">
                        <span>📅 {{ $training->start_date->format('d.m.Y') }}
                            @if($training->start_date->ne($training->end_date))
                                – {{ $training->end_date->format('d.m.Y') }}
                            @endif
                        </span>
                        @if($training->location)
                        <span>📍 {{ $training->location }}</span>
                        @endif
                        @if($training->provider)
                        <span>🏫 {{ $training->provider }}</span>
                        @endif
                        @if($training->cost)
                        <span>💶 {{ number_format($training->cost, 2, ',', '.') }} €</span>
                        @endif
                        @if($training->qualificationType)
                        <span>🎓 Qualifikation: {{ $training->qualificationType->name }}</span>
                        @endif
                    </div>

                    @if($training->description)
                    <p class="text-sm text-gray-600 mt-2">{{ Str::limit($training->description, 150) }}</p>
                    @endif
                </div>

                <div class="ml-6 text-right shrink-0">
                    @if($training->max_participants)
                    <p class="text-sm font-medium {{ $training->isFull() ? 'text-red-600' : 'text-gray-700' }}">
                        {{ $training->freePlaces() === 0 ? 'Ausgebucht' : ($training->freePlaces() . ' Plätze frei') }}
                    </p>
                    @endif

                    {{-- Meine Anmeldung --}}
                    @php $myParticipation = $training->participants->first(); @endphp
                    @if($myParticipation)
                    <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 mt-1 inline-block">
                        Angemeldet: {{ $myParticipation->status->label() }}
                    </span>
                    @else
                    @can('register', $training)
                    <form action="{{ route('personal.trainings.register', $training->id) }}" method="POST" class="mt-1">
                        @csrf
                        <button type="submit"
                                class="text-sm btn-personal-primary {{ $training->isFull() ? 'opacity-50 cursor-not-allowed' : '' }}"
                                @if($training->isFull()) disabled @endif>
                            Anmelden
                        </button>
                    </form>
                    @endcan
                    @endif
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center py-12 text-gray-500">
        <p class="text-4xl mb-3">📚</p>
        <p class="font-medium">Keine anstehenden Fortbildungen</p>
        @can('manage trainings')
        <p class="text-sm mt-2">
            <a href="{{ route('personal.trainings.create') }}" class="text-blue-600 hover:underline">Erste Fortbildung anlegen</a>
        </p>
        @endcan
    </div>
    @endforelse

</div>
@endsection

