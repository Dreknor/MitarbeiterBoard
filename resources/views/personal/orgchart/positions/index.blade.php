@extends('layouts.app')
@push('css') @vite('resources/css/personal.css') @endpush

@section('content')
<div class="personal-wrapper">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Stellen verwalten</h1>
        <div class="flex gap-3">
            <a href="{{ route('personal.orgchart.index') }}" class="btn-personal-secondary text-sm">← Zum Organigramm</a>
            <a href="{{ route('personal.orgchart.positions.create') }}" class="btn-personal-primary text-sm">+ Neue Stelle</a>
        </div>
    </div>

    @if(session('Meldung'))
    <div class="rounded-lg p-4 mb-4 {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-yellow-50 text-yellow-800 border border-yellow-200' }}">
        {{ session('Meldung') }}
    </div>
    @endif

    <div class="personal-card">
        <table class="table-personal">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Übergeordnet</th>
                    <th>Abteilung</th>
                    <th>Ebene</th>
                    <th>Reihenfolge</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                @forelse($positions as $pos)
                <tr>
                    <td class="font-medium">
                        @if($pos->is_leadership) <span class="text-yellow-500">★</span> @endif
                        {{ $pos->name }}
                        @if($pos->color)
                        <span class="inline-block w-3 h-3 rounded-full ml-1" style="background-color: {{ $pos->color }}"></span>
                        @endif
                    </td>
                    <td>{{ $pos->parent?->name ?? '—' }}</td>
                    <td>{{ $pos->department?->name ?? '—' }}</td>
                    <td class="text-center">{{ $pos->level }}</td>
                    <td class="text-center">{{ $pos->sort_order }}</td>
                    <td>
                        <div class="flex gap-2">
                            <a href="{{ route('personal.orgchart.positions.edit', $pos->id) }}"
                               class="text-blue-600 hover:underline text-xs">Bearbeiten</a>
                            <form method="POST" action="{{ route('personal.orgchart.positions.destroy', $pos->id) }}">
                                @csrf @method('DELETE')
                                <button type="submit" onclick="return confirm('Stelle wirklich löschen?')"
                                        class="text-red-600 hover:underline text-xs">Löschen</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-gray-400 py-8">Keine Stellen vorhanden</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
@push('js') @vite('resources/js/personal.js') @endpush

