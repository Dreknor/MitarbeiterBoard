@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@section('content')
<div class="wp-container p-4">

    <a href="{{ route('wp.formatvorlagen.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zur Übersicht
    </a>

    <div class="flex items-center justify-between mb-5">
        <h1 class="text-xl font-bold text-gray-900">Formatvorlage bearbeiten</h1>
        <a href="{{ route('wp.formatvorlagen.vorschau', $wpFormatvorlage) }}" target="_blank"
           class="inline-flex items-center px-3 py-1.5 text-xs bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
            👁️ Vollbild-Vorschau
        </a>
    </div>

    {{-- 2-Spalten-Layout: Formular links, Live-Vorschau rechts --}}
    <div class="flex gap-6 items-start" x-data="formatvorlageEditor()">

        {{-- Formular --}}
        <div class="flex-1 min-w-0">
            <form id="wp-formatvorlage-form"
                  method="POST"
                  action="{{ route('wp.formatvorlagen.update', $wpFormatvorlage) }}"
                  class="space-y-5"
                  data-preview-url="{{ route('wp.formatvorlagen.vorschau-html') }}"
                  @change.debounce.500ms="updatePreview()">
                @csrf @method('PUT')
                @include('wochenplan.new.formatvorlagen._form', ['fv' => $wpFormatvorlage])

                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="px-5 py-2.5 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700">
                        Speichern
                    </button>
                    <a href="{{ route('wp.formatvorlagen.index') }}"
                       class="px-4 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">
                        Abbrechen
                    </a>
                </div>
            </form>
        </div>

        {{-- Live-Vorschau --}}
        <div class="w-96 flex-shrink-0 sticky top-4">
            <div class="bg-white rounded-lg border border-gray-200 p-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-gray-600">Live-Vorschau</span>
                    <span x-show="previewLoading" class="text-xs text-gray-400 italic">Lädt…</span>
                </div>
                <iframe id="wp-formatvorlage-preview"
                        class="w-full rounded border border-gray-100"
                        style="height: 560px; background: #f9fafb;"
                        src="{{ route('wp.formatvorlagen.vorschau', $wpFormatvorlage) }}"
                        title="Formatvorlage Vorschau">
                </iframe>
                <p class="text-xs text-gray-400 mt-2 text-center">
                    Vorschau aktualisiert sich beim Ändern der Einstellungen.
                </p>
            </div>
        </div>

    </div>
</div>
@endsection

@push('js')
    @vite('resources/js/wochenplan.js')
@endpush

