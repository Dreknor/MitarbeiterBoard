@extends('layouts.app')
@push('css') @vite('resources/css/personal.css') @endpush

@section('content')
<div class="personal-wrapper">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Meine Einwilligungen</h1>
        <a href="{{ route('self-service.index') }}" class="btn-personal-secondary text-sm">← Zurück</a>
    </div>

    @if(session('Meldung'))
    <div class="rounded-lg p-4 mb-4 {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-yellow-50 text-yellow-800 border border-yellow-200' }}">
        {{ session('Meldung') }}
    </div>
    @endif

    <p class="text-sm text-gray-500 mb-6">Sie können Ihre Einwilligungen jederzeit widerrufen. Ein Widerruf wirkt sich unmittelbar auf die betroffenen Funktionen aus.</p>

    @foreach($consentTypes as $type)
    @php $consent = $myConsents[$type->id] ?? null; @endphp
    <div class="personal-card mb-4">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <h3 class="font-semibold text-gray-900">{{ $type->name }}</h3>
                <p class="text-sm text-gray-500 mt-1">{{ $type->description }}</p>
                <p class="text-xs text-gray-400 mt-1">Rechtsgrundlage: {{ $type->legal_basis }}</p>
                <div class="mt-2">
                    @if($consent && $consent->isActive())
                    <span class="badge-green">✓ Einwilligung erteilt am {{ $consent->granted_at->format('d.m.Y') }}</span>
                    @else
                    <span class="badge-gray">Keine Einwilligung</span>
                    @endif
                </div>
            </div>
            <div class="shrink-0">
                @if($consent && $consent->isActive())
                <form method="POST" action="{{ route('self-service.consents.revoke', $type) }}">
                    @csrf
                    <button type="submit" onclick="return confirm('Einwilligung wirklich widerrufen?')"
                            class="btn-personal-secondary text-sm text-red-600">Widerrufen</button>
                </form>
                @else
                <form method="POST" action="{{ route('self-service.consents.grant', $type) }}">
                    @csrf
                    <button type="submit" class="btn-personal-primary text-sm">Einwilligung erteilen</button>
                </form>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection
@push('js') @vite('resources/js/personal.js') @endpush

