@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    Qualifikationsmatrix
@endsection

@section('content')
<div class="personal-wrapper">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Qualifikationsmatrix – Pflichtqualifikationen</h1>
        @can('manage qualifications')
            <a href="{{ route('personal.qualification-types.index') }}" class="btn-personal-secondary text-sm">
                ⚙️ Qualifikationstypen verwalten
            </a>
        @endcan
    </div>

    {{-- Legende --}}
    <div class="flex gap-4 mb-4 text-sm">
        <span class="flex items-center gap-1"><span class="w-5 h-5 rounded-full bg-green-500 inline-block"></span> Gültig</span>
        <span class="flex items-center gap-1"><span class="w-5 h-5 rounded-full bg-yellow-500 inline-block"></span> Ablaufend</span>
        <span class="flex items-center gap-1"><span class="w-5 h-5 rounded-full bg-red-500 inline-block"></span> Abgelaufen</span>
        <span class="flex items-center gap-1"><span class="w-5 h-5 rounded-full bg-gray-300 inline-block"></span> Fehlend</span>
    </div>

    <div class="overflow-x-auto bg-white rounded-xl border border-gray-200">
        <table class="w-full text-xs border-collapse">
            <thead>
                <tr class="bg-gray-50">
                    <th class="text-left p-3 font-semibold text-gray-700 sticky left-0 z-10 bg-gray-50 border-b border-r border-gray-200 min-w-40">
                        Mitarbeiter
                    </th>
                    @foreach($types as $type)
                    <th class="p-2 text-center font-semibold text-gray-700 border-b border-gray-200 min-w-20" style="writing-mode: vertical-rl; transform: rotate(180deg); height: 120px; vertical-align: bottom;">
                        <span title="{{ $type->description }}">{{ $type->name }}</span>
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($employees as $employe)
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="p-3 sticky left-0 bg-white font-medium text-gray-900 border-r border-gray-200">
                        <a href="{{ route('personal.qualifications.index', $employe->id) }}"
                           class="hover:text-blue-600">{{ $employe->name }}</a>
                    </td>
                    @foreach($types as $type)
                    @php
                        $qual   = $employe->qualifications->firstWhere('qualification_type_id', $type->id);
                        $status = $qual?->status ?? \App\Enums\QualificationStatus::Fehlend;
                    @endphp
                    <td class="p-2 text-center">
                        <div class="w-6 h-6 rounded-full mx-auto flex items-center justify-center text-white text-xs font-bold
                            {{ $status->value === 'gueltig' ? 'bg-green-500' :
                               ($status->value === 'ablaufend' ? 'bg-yellow-500' :
                               ($status->value === 'abgelaufen' ? 'bg-red-500' : 'bg-gray-300')) }}"
                             title="{{ $type->name }}: {{ $status->label() }}">
                            @if($status->value === 'gueltig') ✓
                            @elseif($status->value === 'ablaufend') ⚠
                            @elseif($status->value === 'abgelaufen') ✗
                            @else –
                            @endif
                        </div>
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400 mt-2">Stand: {{ now()->format('d.m.Y H:i') }} · Gecacht für 5 Minuten</p>

</div>
@endsection

