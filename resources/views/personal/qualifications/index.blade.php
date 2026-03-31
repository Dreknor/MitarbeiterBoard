@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    Qualifikationen – {{ $employe->vorname }} {{ $employe->familienname }}
@endsection

@section('content')
<div class="personal-wrapper">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Qualifikationen</h1>
            <p class="text-gray-500 text-sm mt-1">{{ $employe->name }}</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('personal.qualifications.matrix') }}"
               class="btn-personal-secondary text-sm">📊 Qualifikationsmatrix</a>
            <a href="{{ route('employes.show', $employe->id) }}"
               class="btn-personal-secondary text-sm">← Zurück zur Akte</a>
        </div>
    </div>

    @if(session('Meldung'))
    <div class="rounded-lg p-4 mb-4 {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' }}">
        {{ session('Meldung') }}
    </div>
    @endif

    {{-- Fehlende Pflichtqualifikationen --}}
    @if($missing->isNotEmpty())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
        <h3 class="font-semibold text-red-800 mb-2">⚠️ Fehlende Pflichtqualifikationen ({{ $missing->count() }})</h3>
        <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
            @foreach($missing as $qt)
            <li>{{ $qt->name }}{{ $qt->description ? ' – ' . $qt->description : '' }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Neue Qualifikation eintragen --}}
    @can('manage qualifications')
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Qualifikation eintragen</h2>
        <form action="{{ route('personal.qualifications.store', $employe->id) }}" method="POST"
              class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Qualifikationstyp *</label>
                <select name="qualification_type_id" required class="personal-input w-full">
                    <option value="">– bitte wählen –</option>
                    @foreach($qualificationTypes->groupBy('category') as $cat => $types)
                    <optgroup label="{{ ucfirst($cat) }}">
                        @foreach($types as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </optgroup>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Erworben am *</label>
                <input type="date" name="acquired_date" required class="personal-input w-full">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ablaufdatum (optional)</label>
                <input type="date" name="expiry_date" class="personal-input w-full">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notizen</label>
                <input type="text" name="notes" maxlength="2000" class="personal-input w-full">
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="btn-personal-primary">Speichern</button>
            </div>
        </form>
    </div>
    @endcan

    {{-- Vorhandene Qualifikationen --}}
    @forelse($qualifications as $qual)
    <div class="bg-white rounded-xl border border-gray-200 mb-3">
        <div class="px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                {{-- Ampel --}}
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-lg font-bold
                    {{ $qual->status->value === 'gueltig' ? 'bg-green-500' :
                       ($qual->status->value === 'ablaufend' ? 'bg-yellow-500' :
                       ($qual->status->value === 'abgelaufen' ? 'bg-red-500' : 'bg-gray-400')) }}">
                    {{ $qual->status->value === 'gueltig' ? '✓' : ($qual->status->value === 'ablaufend' ? '⚠' : ($qual->status->value === 'abgelaufen' ? '✗' : '–')) }}
                </div>
                <div>
                    <p class="font-medium text-gray-900">{{ $qual->qualificationType?->name }}</p>
                    <p class="text-sm text-gray-500">
                        Erworben: {{ $qual->acquired_date->format('d.m.Y') }}
                        @if($qual->expiry_date)
                            · Ablauf: <span class="{{ $qual->expiry_date->isPast() ? 'text-red-600 font-medium' : '' }}">{{ $qual->expiry_date->format('d.m.Y') }}</span>
                        @else
                            · Unbegrenzt gültig
                        @endif
                    </p>
                    @if($qual->notes)
                    <p class="text-xs text-gray-400 mt-0.5">{{ $qual->notes }}</p>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs px-2 py-1 rounded-full bg-{{ $qual->status->badgeColor() }}-100 text-{{ $qual->status->badgeColor() }}-700">
                    {{ $qual->status->label() }}
                </span>
                @can('manage qualifications')
                <form action="{{ route('personal.qualifications.destroy', $qual->id) }}" method="POST"
                      onsubmit="return confirm('Qualifikation wirklich löschen?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-500 hover:text-red-700 text-sm">✕</button>
                </form>
                @endcan
            </div>
        </div>
    </div>
    @empty
    <div class="text-center py-12 text-gray-500">
        <p class="text-4xl mb-3">🎓</p>
        <p class="font-medium">Noch keine Qualifikationen erfasst</p>
    </div>
    @endforelse

</div>
@endsection

