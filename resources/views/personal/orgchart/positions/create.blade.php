@extends('layouts.app')
@push('css') @vite('resources/css/personal.css') @endpush

@section('content')
<div class="personal-wrapper">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ isset($position) ? 'Stelle bearbeiten' : 'Neue Stelle' }}</h1>
        <a href="{{ route('personal.orgchart.positions.index') }}" class="btn-personal-secondary text-sm">← Zurück</a>
    </div>

    <div class="personal-card max-w-2xl">
        <form method="POST" action="{{ isset($position) ? route('personal.orgchart.positions.update', $position->id) : route('personal.orgchart.positions.store') }}">
            @csrf
            @if(isset($position)) @method('PUT') @endif

            @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <ul class="list-disc list-inside text-red-700 text-sm">
                    @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                </ul>
            </div>
            @endif

            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                    <input type="text" name="name" class="input-personal" required
                           value="{{ old('name', $position->name ?? '') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Übergeordnete Stelle</label>
                    <select name="parent_position_id" class="input-personal">
                        <option value="">— keine (Wurzelebene) —</option>
                        @foreach($parents as $p)
                        <option value="{{ $p->id }}" {{ old('parent_position_id', $position->parent_position_id ?? '') == $p->id ? 'selected' : '' }}>
                            {{ $p->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Abteilung</label>
                    <select name="department_id" class="input-personal">
                        <option value="">— keine —</option>
                        @foreach($departments as $d)
                        <option value="{{ $d->id }}" {{ old('department_id', $position->department_id ?? '') == $d->id ? 'selected' : '' }}>
                            {{ $d->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reihenfolge</label>
                        <input type="number" name="sort_order" min="0" class="input-personal"
                               value="{{ old('sort_order', $position->sort_order ?? 0) }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Farbe (Hex)</label>
                        <input type="text" name="color" class="input-personal" placeholder="#3B82F6"
                               value="{{ old('color', $position->color ?? '') }}">
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_leadership" value="0">
                    <input type="checkbox" name="is_leadership" value="1" id="is_leadership"
                           {{ old('is_leadership', $position->is_leadership ?? false) ? 'checked' : '' }}>
                    <label for="is_leadership" class="text-sm text-gray-700">Führungsposition (★)</label>
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-6 pt-4 border-t border-gray-100">
                <a href="{{ route('personal.orgchart.positions.index') }}" class="btn-personal-secondary">Abbrechen</a>
                <button type="submit" class="btn-personal-primary">Speichern</button>
            </div>
        </form>
    </div>
</div>
@endsection
@push('js') @vite('resources/js/personal.js') @endpush

