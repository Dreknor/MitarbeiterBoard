@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    Dokumente – {{ $employe->vorname }} {{ $employe->familienname }}
@endsection

@section('content')
<div class="personal-wrapper">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Personalakte – Dokumente</h1>
            <p class="text-gray-500 text-sm mt-1">{{ $employe->name }}</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('personal.personalakte.show', $employe->id) }}"
               class="btn-personal-secondary text-sm">← Zurück zur Akte</a>
        </div>
    </div>

    @if(session('Meldung'))
    <div class="rounded-lg p-4 mb-4 {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : (session('type') === 'info' ? 'bg-blue-50 text-blue-800 border border-blue-200' : 'bg-red-50 text-red-800 border border-red-200') }}">
        {{ session('Meldung') }}
    </div>
    @endif

    {{-- Upload-Formular --}}
    @can('manage personal_documents')
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Dokument hochladen</h2>
        <form action="{{ route('personal.documents.upload', $employe->id) }}" method="POST" enctype="multipart/form-data"
              class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Titel *</label>
                <input type="text" name="title" required maxlength="255"
                       class="personal-input w-full" placeholder="z.B. Führungszeugnis 2025">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Dokumenttyp *</label>
                <select name="document_type_id" required class="personal-input w-full">
                    <option value="">– bitte wählen –</option>
                    @foreach($documentTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Datei * (max. 20 MB)</label>
                <input type="file" name="file" required class="personal-input w-full">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ausstellungsdatum</label>
                <input type="date" name="issue_date" class="personal-input w-full">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ablaufdatum</label>
                <input type="date" name="expiry_date" class="personal-input w-full">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notizen</label>
                <textarea name="notes" rows="2" maxlength="2000" class="personal-input w-full"></textarea>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="btn-personal-primary">
                    📤 Hochladen
                </button>
            </div>
        </form>
    </div>
    @endcan

    {{-- Dokumente nach Kategorie --}}
    @forelse($documents as $category => $docs)
    <div class="bg-white rounded-xl border border-gray-200 mb-4">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800 capitalize">{{ $category }}</h3>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($docs as $doc)
            <div class="px-6 py-4 flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900 truncate">{{ $doc->title }}</p>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ $doc->documentType?->name }}
                        @if($doc->issue_date) · Ausgestellt: {{ $doc->issue_date->format('d.m.Y') }} @endif
                        @if($doc->expiry_date) · Ablauf: <span class="{{ $doc->expiry_date->isPast() ? 'text-red-600 font-medium' : ($doc->expiry_date->diffInDays(now()) < 30 ? 'text-yellow-600 font-medium' : '') }}">{{ $doc->expiry_date->format('d.m.Y') }}</span> @endif
                    </p>
                </div>
                <div class="flex items-center gap-3 ml-4">
                    {{-- Sync-Status --}}
                    <span class="text-xs px-2 py-1 rounded-full
                        {{ $doc->sync_status->value === 'synced' ? 'bg-green-100 text-green-700' :
                           ($doc->sync_status->value === 'pending' || $doc->sync_status->value === 'uploading' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                        {{ $doc->sync_status->label() }}
                    </span>

                    {{-- Download --}}
                    @if($doc->sync_status->value === 'synced')
                    <a href="{{ route('personal.documents.download', $doc->id) }}"
                       class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        ⬇ Download
                    </a>
                    @endif

                    {{-- Löschen --}}
                    @can('manage personal_documents')
                    <form action="{{ route('personal.documents.destroy', $doc->id) }}" method="POST"
                          onsubmit="return confirm('Dokument wirklich löschen?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-500 hover:text-red-700 text-sm">✕</button>
                    </form>
                    @endcan
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @empty
    <div class="text-center py-12 text-gray-500">
        <p class="text-4xl mb-3">📄</p>
        <p class="font-medium">Noch keine Dokumente vorhanden</p>
        <p class="text-sm mt-1">Laden Sie das erste Dokument über das Formular oben hoch.</p>
    </div>
    @endforelse

</div>
@endsection

