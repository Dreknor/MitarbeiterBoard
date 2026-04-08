@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    Fortbildung anlegen
@endsection

@section('content')
<div class="personal-wrapper max-w-2xl">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Fortbildung anlegen</h1>
        <a href="{{ route('personal.trainings.index') }}" class="btn-personal-secondary text-sm">← Zurück</a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
        <ul class="text-sm text-red-700 list-disc list-inside">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form action="{{ route('personal.trainings.store') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Titel *</label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="255"
                       class="personal-input w-full">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                <textarea name="description" rows="4" class="personal-input w-full">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Startdatum *</label>
                    <input type="date" name="start_date" value="{{ old('start_date') }}" required class="personal-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Enddatum *</label>
                    <input type="date" name="end_date" value="{{ old('end_date') }}" required class="personal-input w-full">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Veranstalter</label>
                    <input type="text" name="provider" value="{{ old('provider') }}" maxlength="255" class="personal-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ort</label>
                    <input type="text" name="location" value="{{ old('location') }}" maxlength="255" class="personal-input w-full">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kosten (€)</label>
                    <input type="number" name="cost" value="{{ old('cost') }}" min="0" step="0.01" class="personal-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max. Teilnehmer</label>
                    <input type="number" name="max_participants" value="{{ old('max_participants') }}" min="1" class="personal-input w-full">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Verknüpfte Qualifikation</label>
                <select name="qualification_type_id" class="personal-input w-full">
                    <option value="">– keine –</option>
                    @foreach($qualificationTypes as $type)
                    <option value="{{ $type->id }}" {{ old('qualification_type_id') == $type->id ? 'selected' : '' }}>
                        {{ $type->name }}
                    </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Bei Abschluss wird diese Qualifikation automatisch erneuert.</p>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('personal.trainings.index') }}" class="btn-personal-secondary">Abbrechen</a>
                <button type="submit" class="btn-personal-primary">Fortbildung anlegen</button>
            </div>
        </form>
    </div>

</div>
@endsection

