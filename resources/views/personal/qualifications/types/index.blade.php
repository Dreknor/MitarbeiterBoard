@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    Qualifikationstypen verwalten
@endsection

@section('content')
<div class="personal-wrapper">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Qualifikationstypen</h1>
            <p class="text-gray-500 text-sm mt-1">Vorgaben für die Qualifikationsmatrix</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('personal.qualifications.matrix') }}" class="btn-personal-secondary text-sm">📊 Matrix</a>
            <a href="{{ route('personal.qualification-types.create') }}" class="btn-personal-primary text-sm">+ Neuer Qualifikationstyp</a>
        </div>
    </div>

    @if(session('Meldung'))
    <div class="rounded-lg p-4 mb-4
        {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' :
           (session('type') === 'warning' ? 'bg-yellow-50 text-yellow-800 border border-yellow-200'
                                          : 'bg-red-50 text-red-800 border border-red-200') }}">
        {{ session('Meldung') }}
    </div>
    @endif

    <div class="overflow-x-auto bg-white rounded-xl border border-gray-200">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-700">
                <tr>
                    <th class="text-left p-3 font-semibold">Name</th>
                    <th class="text-left p-3 font-semibold">Kategorie</th>
                    <th class="text-left p-3 font-semibold">Laufzeit</th>
                    <th class="text-left p-3 font-semibold">Erinnerung</th>
                    <th class="text-left p-3 font-semibold">Gilt für</th>
                    <th class="text-left p-3 font-semibold">Status</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($types as $type)
                <tr class="border-t border-gray-100 hover:bg-gray-50 {{ $type->is_active ? '' : 'opacity-60' }}">
                    <td class="p-3 font-medium text-gray-900">
                        {{ $type->name }}
                        @if($type->description)
                            <div class="text-xs text-gray-500 mt-0.5">{{ $type->description }}</div>
                        @endif
                    </td>
                    <td class="p-3">
                        <span class="text-xs px-2 py-1 rounded-full
                            {{ $type->category === 'pflicht' ? 'bg-red-100 text-red-700' :
                               ($type->category === 'empfohlen' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700') }}">
                            {{ ucfirst($type->category) }}
                        </span>
                    </td>
                    <td class="p-3">
                        @if($type->validity_months)
                            {{ $type->validity_months }} Monate
                        @else
                            <span class="text-gray-400">unbegrenzt</span>
                        @endif
                    </td>
                    <td class="p-3">{{ $type->reminder_days ?? 90 }} Tage</td>
                    <td class="p-3 text-xs">
                        @if($type->applies_to)
                            @foreach($type->applies_to as $at)
                                <span class="inline-block bg-blue-50 text-blue-700 rounded px-1.5 py-0.5 mr-1 mb-1">
                                    {{ $employmentTypes[$at] ?? $at }}
                                </span>
                            @endforeach
                        @else
                            <span class="text-gray-400">alle</span>
                        @endif
                    </td>
                    <td class="p-3">
                        @if($type->is_active)
                            <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-700">aktiv</span>
                        @else
                            <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">inaktiv</span>
                        @endif
                    </td>
                    <td class="p-3 text-right whitespace-nowrap">
                        <a href="{{ route('personal.qualification-types.edit', $type->id) }}"
                           class="text-blue-600 hover:text-blue-800 text-sm">Bearbeiten</a>
                        <form action="{{ route('personal.qualification-types.destroy', $type->id) }}"
                              method="POST" class="inline ml-2"
                              onsubmit="return confirm('Qualifikationstyp wirklich löschen? Falls er bereits verwendet wird, wird er nur deaktiviert.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-700 text-sm">Löschen</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="p-6 text-center text-gray-500">
                        Noch keine Qualifikationstypen angelegt.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection

