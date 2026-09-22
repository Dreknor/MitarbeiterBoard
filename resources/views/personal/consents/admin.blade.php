@extends('layouts.app')
@push('css') @vite('resources/css/personal.css') @endpush

@section('content')
<div class="personal-wrapper">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Einwilligungsverwaltung</h1>
    </div>

    <div class="personal-card overflow-x-auto">
        <table class="table-personal">
            <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    @foreach($consentTypes as $type)
                    <th class="text-center">{{ $type->name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr>
                    <td class="font-medium">{{ $user->name }}</td>
                    @foreach($consentTypes as $type)
                    @php
                        $consent = $user->consents->first(fn($c) => $c->consent_type_id === $type->id);
                        $active  = $consent && $consent->isActive();
                    @endphp
                    <td class="text-center">
                        @if($active)
                        <span class="badge-green text-xs">✓</span>
                        @else
                        <span class="badge-gray text-xs">✗</span>
                        @endif
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
@push('js') @vite('resources/js/personal.js') @endpush

