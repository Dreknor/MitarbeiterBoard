@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@section('content')
<div class="wp-wrapper" style="max-width: 860px">
    <a href="{{ route('wp.index') }}" class="wp-back">← Übersicht</a>
    <div class="wp-head">
        <div>
            <h1 class="wp-title">Word-Wochenpläne importieren</h1>
            <p class="wp-subtitle">Liest Wochenpläne nach der Papiervorlage (.docx) ein – auch Dateien mit mehreren Plänen für Lerngruppen und einzelne Kinder.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="wp-note wp-note-warning mb-4">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('wp.import.vorschau') }}" enctype="multipart/form-data" class="wp-card">
        @csrf
        <div class="wp-card-body space-y-4">
            <div>
                <label class="wp-label" for="wp-import-dateien">Word-Dateien (.docx)</label>
                <input id="wp-import-dateien" type="file" name="dateien[]" multiple accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="wp-input" required>
                <p class="wp-hint mt-1">Bis zu 20 Dateien auf einmal (mehr lässt der Server nicht zu – dann in mehreren Durchgängen). Alte .doc-Dateien bitte vorher in Word als „Word-Dokument (*.docx)“ speichern.</p>
            </div>

            <div>
                <span class="wp-label">Klassen für Lerngruppen-Pläne (optional)</span>
                <p class="wp-hint mb-2">Ohne Auswahl werden die Klassen aus der Überschrift erkannt, z. B. „Klasse 2 Her/Wi“ → Herbst 2 und Winter 2. In der Vorschau lässt sich alles noch ändern.</p>
                <div class="grid gap-1 sm:grid-cols-3">
                    @foreach($klassen as $klasse)
                        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="klassen[]" value="{{ $klasse->id }}"> {{ $klasse->name }}</label>
                    @endforeach
                </div>
            </div>

            <div class="wp-note">
                <strong>So wird gelesen:</strong> Überschrift „N. Wochenplan vom …“ (Zeitraum, Klasse/Lerngruppe), „Name: …“ (Kinderplan),
                „Tägliche Übung“ (✍ und 5+5 werden Kürzel), Fächer mit nummerierten Aufgaben (* = freiwillig),
                „So habe ich gearbeitet“ und „Empfehlungen für die Freiarbeit“. Bilder werden nicht übernommen.
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-4 py-3 sm:px-5">
            <a href="{{ route('wp.index') }}" class="wp-btn wp-btn-secondary">Abbrechen</a>
            <button type="submit" class="wp-btn wp-btn-primary">Einlesen &amp; Vorschau</button>
        </div>
    </form>
</div>
@endsection
