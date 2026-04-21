@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    {{ $type->exists ? 'Qualifikationstyp bearbeiten' : 'Neuer Qualifikationstyp' }}
@endsection

@section('content')
<div class="personal-wrapper">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">
            {{ $type->exists ? 'Qualifikationstyp bearbeiten' : 'Neuer Qualifikationstyp' }}
        </h1>
        <a href="{{ route('personal.qualification-types.index') }}" class="btn-personal-secondary text-sm">← Zurück</a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 mb-4">
        <ul class="list-disc list-inside text-sm">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form action="{{ $type->exists ? route('personal.qualification-types.update', $type->id) : route('personal.qualification-types.store') }}"
          method="POST"
          class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        @csrf
        @if($type->exists) @method('PUT') @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                <input type="text" name="name" required maxlength="190"
                       value="{{ old('name', $type->name) }}"
                       class="personal-input w-full">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kategorie *</label>
                <select name="category" required class="personal-input w-full">
                    @foreach(['pflicht' => 'Pflicht', 'empfohlen' => 'Empfohlen', 'optional' => 'Optional'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('category', $type->category) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">Nur <em>Pflicht</em>-Typen erscheinen in der Matrix.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Laufzeit in Monaten <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <input type="number" name="validity_months" min="1" max="600"
                       value="{{ old('validity_months', $type->validity_months) }}"
                       placeholder="z. B. 24 – leer lassen für unbegrenzt"
                       class="personal-input w-full">
                <p class="text-xs text-gray-500 mt-1">
                    Leer lassen für unbegrenzt gültige Qualifikationen. Bei Auffrischungsfortbildungen wird das Ablaufdatum automatisch daraus berechnet.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Erinnerung (Tage vor Ablauf)</label>
                <input type="number" name="reminder_days" min="0" max="365"
                       value="{{ old('reminder_days', $type->reminder_days ?? 90) }}"
                       class="personal-input w-full">
            </div>

            <div>
                <label class="flex items-center gap-2 mt-6">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $type->is_active ?? true))>
                    <span class="text-sm font-medium text-gray-700">Aktiv</span>
                </label>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Gilt für Anstellungstypen <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    @php $selected = old('applies_to', $type->applies_to ?? []); @endphp
                    @foreach($employmentTypes as $val => $label)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="applies_to[]" value="{{ $val }}"
                                   @checked(in_array($val, (array) $selected, true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-500 mt-1">Leer = gilt für alle Anstellungsarten.</p>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                <textarea name="description" rows="3" maxlength="2000"
                          class="personal-input w-full">{{ old('description', $type->description) }}</textarea>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
            <a href="{{ route('personal.qualification-types.index') }}" class="btn-personal-secondary">Abbrechen</a>
            <button type="submit" class="btn-personal-primary">Speichern</button>
        </div>
    </form>

</div>
@endsection

