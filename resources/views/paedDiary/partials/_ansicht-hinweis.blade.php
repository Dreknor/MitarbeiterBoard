{{--
    Pädagogisches Tagebuch – Hinweis auf die neue Ansicht (nur in der bisherigen Ansicht).
    Erwartet $darstellung (User::paedDiaryDarstellung()). Solange der Hinweis nicht weggeklickt
    wurde, erscheint er als Karte; danach bleibt nur der kleine Umschalter.
--}}
@php($darstellung = $darstellung ?? auth()->user()->paedDiaryDarstellung())

@if(!$darstellung['hinweis_ausgeblendet'])
    <div class="pd-ansicht-hinweis" role="region" aria-label="Neue Ansicht verfügbar">
        <div class="pd-ansicht-hinweis__text">
            <strong>Neu: eine überarbeitete Ansicht des Pädagogischen Tagebuchs.</strong>
            <span>Wahlweise kompakt oder als Tageskarten für das iPad, Notizen in Kategoriefarben, eine übersichtlichere Schülerakte mit Spinnennetz der Reflexion. Du kannst jederzeit zurückwechseln.</span>
        </div>
        <div class="pd-ansicht-hinweis__aktionen">
            <form method="post" action="{{ route('paedDiary.settings.darstellung') }}">
                @csrf
                <input type="hidden" name="ansicht" value="neu">
                <button type="submit" class="btn btn-primary btn-sm">Neue Ansicht ausprobieren</button>
            </form>
            <form method="post" action="{{ route('paedDiary.settings.darstellung') }}">
                @csrf
                <input type="hidden" name="hinweis_ausgeblendet" value="1">
                <button type="submit" class="btn btn-link btn-sm">Später</button>
            </form>
        </div>
    </div>
@else
    <form method="post" action="{{ route('paedDiary.settings.darstellung') }}" class="pd-ansicht-umschalter">
        @csrf
        <input type="hidden" name="ansicht" value="neu">
        <button type="submit" class="btn btn-outline-primary btn-sm" title="Zur neuen Ansicht wechseln">
            <i class="fas fa-magic" aria-hidden="true"></i> Neue Ansicht
        </button>
    </form>
@endif

@once
    @push('css')
        <style>
            .pd-ansicht-hinweis { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px;
                background:#eef3ff; border:1px solid #c7d6fb; border-radius:8px; padding:12px 16px; margin-bottom:12px; }
            .pd-ansicht-hinweis__text { display:flex; flex-direction:column; gap:2px; min-width:0; flex:1 1 320px; font-size:.9rem; color:#1e293b; }
            .pd-ansicht-hinweis__aktionen { display:flex; align-items:center; gap:4px; }
            .pd-ansicht-hinweis__aktionen form, .pd-ansicht-umschalter { margin:0; }
            .pd-ansicht-umschalter { display:flex; justify-content:flex-end; margin-bottom:8px; }
        </style>
    @endpush
@endonce
