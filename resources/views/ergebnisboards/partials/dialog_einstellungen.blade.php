{{-- Einstellungen: Verknüpfung (Schreibrecht), Kopie, Archiv, Löschen (Verwaltungsrecht) --}}
@php
    $meetingListe = collect($meetings);
    if ($board->meeting && ! $meetingListe->contains('id', $board->meeting->id)) {
        $meetingListe->prepend(['id' => $board->meeting->id, 'titel' => $daten['meeting']['titel'] ?? 'Sitzung']);
    }
    $themenListe = collect($themen);
    if ($board->theme && ! $themenListe->contains('id', $board->theme->id)) {
        $themenListe->prepend(['id' => $board->theme->id, 'titel' => $board->theme->theme]);
    }
@endphp
<div class="erg-backdrop" x-show.important="dialog === 'einstellungen'" x-cloak @click.self="dialogSchliessen()">
    <div class="erg-dialog" data-dialog="einstellungen" role="dialog" aria-modal="true" aria-labelledby="erg-einst-titel"
         @keydown.tab="fokusFalle($event)">
        <div class="erg-dialog-kopf">
            <div>
                <h2 id="erg-einst-titel" class="erg-dialog-titel">Einstellungen</h2>
                <p class="text-xs text-slate-500">
                    Erstellt von {{ $daten['ersteller'] ?? '–' }} · Vorlage „{{ $daten['vorlage_titel'] }}“
                </p>
            </div>
            <button type="button" class="erg-icon-btn" @click="dialogSchliessen()" aria-label="Dialog schließen"><i class="fas fa-times"></i></button>
        </div>
        <div class="erg-dialog-body">
            @if($daten['darf_schreiben'])
                <div class="erg-abschnitt">
                    <div class="erg-abschnitt-titel"><i class="fas fa-link"></i> Verknüpfung</div>
                    <div class="erg-alert erg-alert-red" x-show.important="einstellungenFehler" role="alert">
                        <i class="fas fa-exclamation-circle mt-0.5"></i><span x-text="einstellungenFehler"></span>
                    </div>
                    <div>
                        <label class="erg-label" for="erg-e-meeting">Sitzung</label>
                        <select id="erg-e-meeting" class="erg-select" x-model="verknuepfung.meeting_id">
                            <option value="">– keine –</option>
                            @foreach($meetingListe as $m)
                                <option value="{{ $m['id'] }}">{{ $m['titel'] }}</option>
                            @endforeach
                        </select>
                        <template x-if="board.meeting">
                            <a :href="board.meeting.url" class="erg-hint inline-flex items-center gap-1 text-blue-700"><i class="fas fa-external-link-alt"></i> Zur Sitzung</a>
                        </template>
                    </div>
                    <div>
                        <label class="erg-label" for="erg-e-theme">Thema</label>
                        <select id="erg-e-theme" class="erg-select" x-model="verknuepfung.theme_id">
                            <option value="">– keines –</option>
                            @foreach($themenListe as $t)
                                <option value="{{ $t['id'] }}">{{ $t['titel'] }}</option>
                            @endforeach
                        </select>
                        <p class="erg-hint">Nötig für „Karte → Aufgabe“: Aufgaben entstehen in diesem Thema.</p>
                    </div>
                    <button type="button" class="erg-btn erg-btn-primary erg-btn-sm self-start" @click="verknuepfungSpeichern()" :disabled="laedt">
                        <i class="fas fa-save"></i> Verknüpfung speichern
                    </button>
                </div>
            @endif

            <div class="erg-abschnitt">
                <div class="erg-abschnitt-titel"><i class="fas fa-tools"></i> Aktionen</div>
                <div class="flex flex-wrap gap-2">
                    @if($darfDuplizieren)
                        <form method="post" action="{{ route('ergebnisboards.duplicate', $board) }}">
                            @csrf
                            <button type="submit" class="erg-btn erg-btn-secondary erg-btn-sm" title="Karten werden ohne Aufgaben, Anhänge und Zuständige übernommen">
                                <i class="far fa-clone"></i> Duplizieren
                            </button>
                        </form>
                    @endif
                    @if($daten['darf_verwalten'])
                        <form method="post" action="{{ route('ergebnisboards.archive', $board) }}">
                            @csrf
                            <input type="hidden" name="archivieren" value="{{ $daten['archiviert'] ? 0 : 1 }}">
                            <button type="submit" class="erg-btn erg-btn-secondary erg-btn-sm">
                                @if($daten['archiviert'])
                                    <i class="fas fa-undo"></i> Wiederherstellen
                                @else
                                    <i class="fas fa-archive"></i> Archivieren
                                @endif
                            </button>
                        </form>
                        <form method="post" action="{{ route('ergebnisboards.destroy', $board) }}"
                              onsubmit="return confirm('Board „{{ addslashes($board->titel) }}“ mit allen Karten und Anhängen endgültig löschen?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="erg-btn erg-btn-danger erg-btn-sm"><i class="far fa-trash-alt"></i> Löschen</button>
                        </form>
                    @endif
                </div>
                @if($darfDuplizieren)
                    <p class="erg-hint mt-0">Duplizieren übernimmt Felder und Karten – ohne Aufgaben, Anhänge und Zuständige.</p>
                @endif
            </div>

            @can('manageTemplates', App\Models\Ergebnisboard::class)
                <form method="post" action="{{ route('ergebnisboards.als-vorlage', $board) }}" class="erg-abschnitt">
                    @csrf
                    <div class="erg-abschnitt-titel"><i class="fas fa-shapes"></i> Als Vorlage speichern</div>
                    <p class="erg-hint mt-0">Übernimmt das Raster dieses Boards (Spalten, Felder, Achsen, Legende) – ohne Karten. Danach öffnet sich der Vorlagen-Editor.</p>
                    <div>
                        <label class="erg-label" for="erg-e-vorlage-titel">Titel der Vorlage <span class="erg-required">*</span></label>
                        <input id="erg-e-vorlage-titel" name="titel" type="text" class="erg-input" required maxlength="255" value="{{ $board->titel }}">
                    </div>
                    <div>
                        <label class="erg-label" for="erg-e-vorlage-beschreibung">Beschreibung</label>
                        <input id="erg-e-vorlage-beschreibung" name="beschreibung" type="text" class="erg-input" maxlength="500" value="{{ $board->untertitel }}">
                    </div>
                    <button type="submit" class="erg-btn erg-btn-secondary erg-btn-sm self-start"><i class="fas fa-save"></i> Als Vorlage speichern</button>
                </form>
            @endcan
        </div>
        <div class="erg-dialog-fuss">
            <span></span>
            <button type="button" class="erg-btn erg-btn-ghost" @click="dialogSchliessen()">Schließen</button>
        </div>
    </div>
</div>
