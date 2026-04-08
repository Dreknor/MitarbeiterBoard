@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    Fortbildung bearbeiten
@endsection

@section('content')
<div class="personal-wrapper max-w-2xl">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Fortbildung bearbeiten</h1>
        <a href="{{ route('personal.trainings.show', $training->id) }}" class="btn-personal-secondary text-sm">← Zurück</a>
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
        <form action="{{ route('personal.trainings.update', $training->id) }}" method="POST" class="space-y-4">
            @csrf @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Titel *</label>
                <input type="text" name="title" value="{{ old('title', $training->title) }}" required maxlength="255"
                       class="personal-input w-full">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                <textarea name="description" rows="4" class="personal-input w-full">{{ old('description', $training->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Startdatum *</label>
                    <input type="date" name="start_date" value="{{ old('start_date', $training->start_date->format('Y-m-d')) }}" required class="personal-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Enddatum *</label>
                    <input type="date" name="end_date" value="{{ old('end_date', $training->end_date->format('Y-m-d')) }}" required class="personal-input w-full">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Veranstalter</label>
                    <input type="text" name="provider" value="{{ old('provider', $training->provider) }}" maxlength="255" class="personal-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ort</label>
                    <input type="text" name="location" value="{{ old('location', $training->location) }}" maxlength="255" class="personal-input w-full">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kosten (€)</label>
                    <input type="number" name="cost" value="{{ old('cost', $training->cost) }}" min="0" step="0.01" class="personal-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max. Teilnehmer</label>
                    <input type="number" name="max_participants" value="{{ old('max_participants', $training->max_participants) }}" min="1" class="personal-input w-full">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                    <select name="status" required class="personal-input w-full">
                        @foreach(\App\Enums\TrainingStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ old('status', $training->status->value) === $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Verknüpfte Qualifikation</label>
                    <select name="qualification_type_id" class="personal-input w-full">
                        <option value="">– keine –</option>
                        @foreach($qualificationTypes as $type)
                        <option value="{{ $type->id }}" {{ old('qualification_type_id', $training->qualification_type_id) == $type->id ? 'selected' : '' }}>
                            {{ $type->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex justify-between gap-3 pt-4 border-t border-gray-100">
                @can('delete', $training)
                <form action="{{ route('personal.trainings.destroy', $training->id) }}" method="POST"
                      onsubmit="return confirm('Fortbildung wirklich löschen?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-personal-danger text-sm">Löschen</button>
                </form>
                @endcan
                <div class="flex gap-3 ml-auto">
                    <a href="{{ route('personal.trainings.show', $training->id) }}" class="btn-personal-secondary">Abbrechen</a>
                    <button type="submit" class="btn-personal-primary">Speichern</button>
                </div>
            </div>
        </form>
    </div>

</div>
@endsection

