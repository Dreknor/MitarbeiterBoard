@extends('layouts.app')

@push('css')
    @vite(['resources/css/ergebnisboards.css', 'resources/js/ergebnisboards.js'])
@endpush

@php
    $platzhalter = ['board' => $board->id, 'karte' => '__KARTE__'];
    $konfig = [
        'daten' => $daten,
        'personen' => $personen,
        'farben' => $farben,
        'userId' => $userId ?? auth()->id(),
        'maxDateiKb' => $maxDateiKb,
        'urls' => [
            'update' => route('ergebnisboards.update', $board),
            'bereiche' => route('ergebnisboards.bereiche', $board),
            'daten' => route('ergebnisboards.daten', $board),
            'zugriffe' => route('ergebnisboards.zugriffe', $board),
            'sortierung' => route('ergebnisboards.sortierung', $board),
            'kartenStore' => route('ergebnisboards.karten.store', $board),
            'kartenUpdate' => route('ergebnisboards.karten.update', $platzhalter),
            'kartenDestroy' => route('ergebnisboards.karten.destroy', $platzhalter),
            'aufgabe' => route('ergebnisboards.karten.aufgabe', $platzhalter),
            'aufgabeErledigen' => route('ergebnisboards.karten.aufgabe.erledigen', $platzhalter),
            'dateienStore' => route('ergebnisboards.karten.dateien.store', $platzhalter),
            'dateienDestroy' => route('ergebnisboards.karten.dateien.destroy', $platzhalter + ['media' => '__MEDIA__']),
            'verlauf' => route('ergebnisboards.verlauf', $board),
            'wiederherstellen' => route('ergebnisboards.karten.wiederherstellen', ['board' => $board->id, 'geloeschteKarte' => '__KARTE__']),
            'kommentare' => route('ergebnisboards.kommentare.index', $board),
            'kommentarUpdate' => route('ergebnisboards.kommentare.update', ['board' => $board->id, 'kommentar' => '__KOMMENTAR__']),
            'kommentarDestroy' => route('ergebnisboards.kommentare.destroy', ['board' => $board->id, 'kommentar' => '__KOMMENTAR__']),
        ],
    ];
    $darfVerwalten = $daten['darf_verwalten'];
    $hatEinstellungen = $daten['darf_schreiben'] || $darfVerwalten || $darfDuplizieren
        || auth()->user()->can('manageTemplates', \App\Models\Ergebnisboard::class);
@endphp

@section('content')
<div class="erg-wrapper" x-data="ergebnisboard(@js($konfig))"
     @keydown.escape.window="if (dialog) { dialogSchliessen() }"
     @erg-zugriffe="zugriffEintraege = $event.detail">

    {{-- Werkzeugleiste (nicht im Export) --}}
    <div class="erg-toolbar">
        <a href="{{ route('ergebnisboards.index') }}" class="erg-back">
            <i class="fas fa-arrow-left"></i> Boards
        </a>

        <div class="erg-toolbar-gruppe">
            <button type="button" class="erg-btn erg-btn-ghost erg-btn-sm" data-tour-start="ergebnisboards" title="Kurze Einführung">
                <i class="far fa-question-circle"></i> <span class="hidden sm:inline">Einführung</span>
            </button>

            <button type="button" class="erg-btn erg-btn-secondary erg-btn-sm" :class="{ 'is-aktiv': nurMeine }"
                    @click="nurMeine = !nurMeine" :aria-pressed="nurMeine.toString()"
                    title="Nur Karten zeigen, für die ich zuständig bin">
                <i class="fas fa-user-check"></i> Nur meine
                <span class="text-[10px] opacity-70" x-show.important="nurMeine" x-text="'(' + meineAnzahl + ')'"></span>
            </button>

            <div class="erg-segment" role="group" aria-label="Farbschema">
                @foreach($farbschemata as $schluessel => $label)
                    <button type="button" :class="{ 'is-active': board.farbschema === '{{ $schluessel }}' }"
                            :aria-pressed="(board.farbschema === '{{ $schluessel }}').toString()"
                            @click="farbschemaSetzen('{{ $schluessel }}')">{{ $label }}</button>
                @endforeach
            </div>

            <template x-if="board.darf_schreiben">
                <button type="button" class="erg-btn erg-btn-sm" :class="bearbeiten ? 'erg-btn-primary' : 'erg-btn-secondary'"
                        @click="bearbeiten = !bearbeiten" :aria-pressed="bearbeiten.toString()" data-tour="erg-bearbeiten">
                    <i class="fas" :class="bearbeiten ? 'fa-check' : 'fa-pen'"></i>
                    <span x-text="bearbeiten ? 'Fertig' : 'Bearbeiten'"></span>
                </button>
            </template>

            <button type="button" class="erg-btn erg-btn-secondary erg-btn-sm" x-show.important="schreibbar"
                    @click="felderOeffnen()" data-tour="erg-felder">
                <i class="fas fa-th-list"></i> Felder
            </button>

            <button type="button" class="erg-btn erg-btn-secondary erg-btn-sm" @click="seitenleisteOeffnen('kommentare')"
                    data-tour="erg-kommentare" title="Kommentare zum Board und Änderungsverlauf">
                <i class="far fa-comments"></i> <span class="hidden md:inline">Kommentare &amp; Verlauf</span>
                <span class="erg-zaehler" x-show.important="board.kommentare_board" x-text="board.kommentare_board"></span>
            </button>

            @if($darfVerwalten && $optionen)
                <button type="button" class="erg-btn erg-btn-secondary erg-btn-sm" @click="zugriffOeffnen()" data-tour="erg-zugriff">
                    <i class="fas fa-user-shield"></i> Zugriff
                </button>
            @endif

            @if($hatEinstellungen)
                <button type="button" class="erg-btn erg-btn-secondary erg-btn-sm" @click="einstellungenOeffnen()" title="Verknüpfung, Archiv, Kopie">
                    <i class="fas fa-cog"></i> <span class="hidden md:inline">Einstellungen</span>
                </button>
            @endif

            @if(!empty($teilenUrl))
                <a href="{{ $teilenUrl }}" class="erg-btn erg-btn-secondary erg-btn-sm" title="Als Nachricht ans Kollegium teilen">
                    <i class="far fa-paper-plane"></i> <span class="hidden md:inline">Teilen</span>
                </a>
            @endif

            <span class="inline-flex gap-1" data-tour="erg-export">
                <button type="button" class="erg-btn erg-btn-primary erg-btn-sm" @click="pngExport()" :disabled="laedt">
                    <i class="fas fa-image"></i> PNG
                </button>
                <a href="{{ route('ergebnisboards.pdf', $board) }}" target="_blank" rel="noopener" class="erg-btn erg-btn-secondary erg-btn-sm">
                    <i class="far fa-file-pdf"></i> PDF
                </a>
            </span>
        </div>
    </div>

    <template x-if="board.archiviert">
        <div class="erg-alert erg-alert-amber mb-4">
            <i class="fas fa-archive mt-0.5"></i>
            <div class="flex-1">Dieses Board ist archiviert und kann nicht mehr bearbeitet werden.</div>
            @if($darfVerwalten)
                <form method="post" action="{{ route('ergebnisboards.archive', $board) }}">
                    @csrf
                    <input type="hidden" name="archivieren" value="0">
                    <button type="submit" class="erg-btn erg-btn-secondary erg-btn-sm"><i class="fas fa-undo"></i> Wiederherstellen</button>
                </form>
            @endif
        </div>
    </template>

    <p class="erg-hint mb-3" x-show.important="schreibbar">
        <i class="fas fa-info-circle"></i>
        Bearbeitungsmodus: Texte im Kopf direkt anklicken, Karten anklicken zum Bearbeiten, per Drag &amp; Drop verschieben.
        <span x-show.important="nurMeine" class="text-amber-700 font-semibold">Verschieben ist bei „Nur meine“ abgeschaltet.</span>
    </p>

    <div class="erg-board-scroll">
        @include('ergebnisboards.partials.board')
    </div>

    @include('ergebnisboards.partials.dialog_karte')
    @include('ergebnisboards.partials.dialog_felder')
    @if($darfVerwalten && $optionen)
        @include('ergebnisboards.partials.dialog_zugriff')
    @endif
    @if($hatEinstellungen)
        @include('ergebnisboards.partials.dialog_einstellungen')
    @endif
    @include('ergebnisboards.partials.dialog_export')
    @include('ergebnisboards.partials.seitenleiste')

    {{-- Toasts --}}
    <div class="erg-toasts" aria-live="polite">
        <template x-for="t in toasts" :key="t.id">
            <div class="erg-toast" :class="{ 'is-fehler': t.art === 'fehler', 'is-warnung': t.art === 'warnung', 'is-ok': t.art === 'ok' }" role="status">
                <span class="erg-spinner" x-show.important="t.art === 'laedt'"></span>
                <i class="fas fa-check-circle" x-show.important="t.art === 'ok'"></i>
                <i class="fas fa-exclamation-triangle" x-show.important="t.art === 'fehler' || t.art === 'warnung'"></i>
                <span x-text="t.text"></span>
                <button type="button" class="ml-1 opacity-70 hover:opacity-100" @click="toastWeg(t.id)" aria-label="Meldung schließen">&times;</button>
            </div>
        </template>
    </div>
</div>

<x-tour id="ergebnisboards" :version="2" :steps="[
    ['title' => 'Board', 'text' => 'Ein Board hält Ergebnisse aus Sitzungen, pädagogischen Tagen oder Projekten fest – als Felder mit Karten. Klicken Sie eine Karte an, um Details, Zuständige, Fälligkeit und Anhänge zu sehen.'],
    $daten['darf_schreiben'] ? ['target' => 'erg-bearbeiten', 'title' => 'Bearbeiten', 'text' => 'Schalten Sie den Bearbeitungsmodus ein: Dann lassen sich Titel, Untertitel und „Stand / Gremium“ direkt im Board ändern und in jedem Feld neue Karten anlegen.'] : null,
    ['selector' => '.erg-karte', 'title' => 'Karten', 'text' => 'Karten zeigen Etikett, Fälligkeit (rot = überfällig), Zuständige und Anhänge. Der Stern markiert einen Arbeitsschwerpunkt. Bei verknüpftem Thema kann aus einer Karte eine Aufgabe werden.'],
    $daten['darf_schreiben'] ? ['selector' => '.erg-raster', 'title' => 'Drag & Drop', 'text' => 'Im Bearbeitungsmodus ziehen Sie Karten innerhalb eines Feldes oder in ein anderes Feld. Landet eine Karte mit Aufgabe in „Erledigt“, kann die Aufgabe direkt abgeschlossen werden.'] : null,
    $daten['darf_schreiben'] ? ['target' => 'erg-felder', 'title' => 'Felder', 'text' => 'Felder umbenennen, einfärben, Unterbereiche ergänzen – bei Kanban und freien Spalten auch Spalten hinzufügen, entfernen und umsortieren.'] : null,
    $darfVerwalten ? ['target' => 'erg-zugriff', 'title' => 'Zugriff', 'text' => 'Legen Sie fest, welche Personen, Gruppen oder Rollen das Board lesen oder bearbeiten dürfen.'] : null,
    ['target' => 'erg-kommentare', 'title' => 'Kommentare & Verlauf', 'text' => 'Hier kommentieren Sie das Board als Ganzes und sehen alle Änderungen. Kommentare zu einer einzelnen Karte und deren Verlauf finden Sie im Karten-Dialog. Mit @Name erwähnen Sie Kolleg:innen – sie werden benachrichtigt.'],
    ['target' => 'erg-export', 'title' => 'Export', 'text' => 'Als PNG-Grafik (z. B. für Präsentationen oder Nachrichten) oder als PDF im A4-Querformat herunterladen.'],
]" />
@endsection
