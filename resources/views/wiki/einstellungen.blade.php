@extends('wiki.layout')

@section('wiki')
<div class="flex flex-col gap-5">
    @include('wiki.partials.seitenkopf', ['untertitel' => 'Schutz & Sichtbarkeit'])

    <section class="wiki-karte">
        <div class="wiki-karte-kopf"><h2 class="wiki-karte-titel"><i class="fas fa-shield-alt"></i> Schutz</h2></div>
        <form method="POST" action="{{ route('wiki.schutz', $seite) }}" class="wiki-karte-inhalt flex flex-col gap-3">
            @csrf
            @if($seite->systemseite)
                <div class="wiki-alert wiki-alert-blau"><i class="fas fa-info-circle mt-0.5"></i><div>Systemseiten (Handbücher der Module) sind immer geschützt. Änderungen werden beim nächsten Update überschrieben.</div></div>
            @else
                <label class="wiki-check">
                    <input type="hidden" name="geschuetzt" value="0">
                    <input type="checkbox" name="geschuetzt" value="1" @checked($seite->geschuetzt)>
                    Nur die Wiki-Verwaltung darf diese Seite bearbeiten
                </label>
                <div><button class="wiki-btn wiki-btn-primary wiki-btn-sm"><i class="fas fa-check"></i> Speichern</button></div>
            @endif
        </form>
    </section>

    <section class="wiki-karte">
        <div class="wiki-karte-kopf"><h2 class="wiki-karte-titel"><i class="fas fa-lock"></i> Sichtbarkeit</h2></div>
        <form method="POST" action="{{ route('wiki.einschraenkung', $seite) }}" class="wiki-karte-inhalt flex flex-col gap-4">
            @csrf
            <p class="text-sm text-gray-600">
                Ohne Auswahl sehen alle mit Leserecht die Seite. Mit Auswahl sehen sie nur die gewählten Rollen und Gruppen (und die Wiki-Verwaltung) –
                auch in Suche, Seitenbaum und Dashboard. Die Einschränkung gilt automatisch für alle Unterseiten.
            </p>
            @if($geerbtVon)
                <div class="wiki-alert wiki-alert-gelb"><i class="fas fa-level-up-alt mt-0.5"></i>
                    <div>Diese Seite ist bereits über <a href="{{ $geerbtVon->url() }}" class="font-semibold underline">{{ $geerbtVon->titel }}</a> eingeschränkt. Hier können Sie sie nur weiter einschränken.</div>
                </div>
            @endif
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <div class="wiki-label">Rollen</div>
                    <div class="wiki-auswahlliste">
                        @foreach($rollen as $rolle)
                            <label class="wiki-check"><input type="checkbox" name="rollen[]" value="{{ $rolle->id }}" @checked(in_array($rolle->id, $gewaehlteRollen))> {{ $rolle->name }}</label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <div class="wiki-label">Gruppen</div>
                    <div class="wiki-auswahlliste">
                        @foreach($gruppen as $gruppe)
                            <label class="wiki-check"><input type="checkbox" name="gruppen[]" value="{{ $gruppe->id }}" @checked(in_array($gruppe->id, $gewaehlteGruppen))> {{ $gruppe->name }}</label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div><button class="wiki-btn wiki-btn-primary wiki-btn-sm"><i class="fas fa-check"></i> Sichtbarkeit speichern</button></div>
        </form>
    </section>

    @can('delete', $seite)
        <section class="wiki-karte">
            <div class="wiki-karte-kopf"><h2 class="wiki-karte-titel"><i class="fas fa-trash-alt"></i> Löschen</h2></div>
            <form method="POST" action="{{ route('wiki.loeschen', $seite) }}" class="wiki-karte-inhalt flex flex-wrap items-center gap-3" onsubmit="return confirm('Seite in den Papierkorb verschieben?')">
                @csrf @method('DELETE')
                <p class="flex-1 text-sm text-gray-600">Die Seite wandert in den Papierkorb und kann dort jederzeit wiederhergestellt werden. Seiten mit Unterseiten, die Startseite und Systemseiten lassen sich nicht löschen.</p>
                <button class="wiki-btn wiki-btn-danger wiki-btn-sm" @disabled($seite->istStartseite() || $seite->systemseite)><i class="fas fa-trash-alt"></i> In den Papierkorb</button>
            </form>
            @error('seite')<p class="wiki-fehler px-6 pb-4">{{ $message }}</p>@enderror
        </section>
    @endcan
</div>
@endsection
