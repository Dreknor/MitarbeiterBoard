@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    Nextcloud Sync-Fehler
@endsection

@section('content')
<div class="personal-wrapper">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Nextcloud Sync-Fehler</h1>
    </div>

    @if(session('Meldung'))
    <div class="rounded-lg p-4 mb-4 {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' }}">
        {{ session('Meldung') }}
    </div>
    @endif

    @if($documents->isEmpty())
    <div class="text-center py-12 text-gray-500">
        <p class="text-4xl mb-3">✅</p>
        <p class="font-medium">Keine Sync-Fehler vorhanden</p>
    </div>
    @else
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="divide-y divide-gray-100">
            @foreach($documents as $doc)
            <div class="px-6 py-4 flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900">{{ $doc->title }}</p>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ $doc->employe?->name }} · {{ $doc->documentType?->name }}
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5 font-mono">{{ $doc->nextcloud_path }}</p>
                </div>
                <div class="ml-4">
                    <form action="{{ route('personal.documents.sync-retry', $doc->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-personal-secondary text-sm">
                            🔄 Erneut versuchen
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    {{ $documents->links() }}
    @endif

</div>
@endsection

