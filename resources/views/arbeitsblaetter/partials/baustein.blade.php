{{-- Ein Baustein im Prüf-Editor (Alpine abEditor). $ebene 0 = Hauptliste (Variable b), 1 = Teilaufgabe (Variable k) --}}
@php $v = $ebene ? 'k' : 'b'; @endphp
<div class="ab-baustein-kopf">
    <span class="ab-griffleiste" title="Ziehen zum Verschieben" x-show="blatt.darf_bearbeiten"><i class="fas fa-grip-vertical"></i></span>
    <span class="ab-typ"><i class="fas" :class="icon({{ $v }}.typ)"></i>
        <select class="bg-transparent text-xs font-semibold" :value="{{ $v }}.typ" @change="typAendern({{ $v }}, $event.target.value)" :disabled="!blatt.darf_bearbeiten" aria-label="Typ">
            <template x-for="(label, typ) in typen" :key="typ">
                <option :value="typ" x-text="label" :selected="typ === {{ $v }}.typ"></option>
            </template>
        </select>
    </span>
    <template x-if="{{ $v }}.typ === 'aufgabe'">
        <input type="text" class="ab-input ab-input-sm w-16" x-model="{{ $v }}.nummer" @input="geaendert()" maxlength="10" placeholder="Nr." aria-label="Aufgabennummer" :disabled="!blatt.darf_bearbeiten">
    </template>
    <div class="ml-auto flex items-center gap-0.5" x-show="blatt.darf_bearbeiten">
        <button type="button" class="ab-icon-btn" @click.stop="verschieben({{ $v }}.id, -1)" title="Nach oben"><i class="fas fa-arrow-up"></i></button>
        <button type="button" class="ab-icon-btn" @click.stop="verschieben({{ $v }}.id, 1)" title="Nach unten"><i class="fas fa-arrow-down"></i></button>
        @if($ebene === 0)
            <button type="button" class="ab-icon-btn" @click.stop="gruppieren({{ $v }}.id)" x-show="kannGruppieren({{ $v }}.id)" title="Als Teil der Aufgabe darüber (Aufgabe nie teilen)"><i class="fas fa-level-up-alt fa-rotate-90"></i></button>
        @else
            <button type="button" class="ab-icon-btn" @click.stop="loesen({{ $v }}.id)" title="Aus der Aufgabe lösen"><i class="fas fa-level-down-alt fa-rotate-90"></i></button>
        @endif
        <button type="button" class="ab-icon-btn hover:text-red-600" @click.stop="loeschen({{ $v }}.id)" title="Baustein löschen"><i class="fas fa-trash-alt"></i></button>
    </div>
</div>

{{-- Überschrift / Anweisung --}}
<template x-if="{{ $v }}.typ === 'ueberschrift' || {{ $v }}.typ === 'anweisung'">
    <textarea class="ab-textarea" rows="1" x-model="{{ $v }}.text" @input="geaendert(); hoehe($event.target)" x-init="$nextTick(() => hoehe($el))"
              :class="{{ $v }}.typ === 'ueberschrift' && 'font-bold text-lg'" :disabled="!blatt.darf_bearbeiten" aria-label="Text"></textarea>
</template>

{{-- Text / Aufgabe mit Lücken --}}
<template x-if="{{ $v }}.typ === 'text' || {{ $v }}.typ === 'aufgabe'">
    <div>
        <textarea class="ab-textarea" rows="2" :value="segText({{ $v }})" @input="segSetzen({{ $v }}, $event.target.value); hoehe($event.target)"
                  x-init="$nextTick(() => hoehe($el))" :disabled="!blatt.darf_bearbeiten" aria-label="Text mit Lücken"></textarea>
        <div class="flex items-center gap-2 mt-1" x-show="blatt.darf_bearbeiten">
            <button type="button" class="ab-btn ab-btn-ghost ab-btn-sm" @click.stop="lueckeEinfuegen({{ $v }}, $event)"><i class="fas fa-i-cursor"></i> Lücke einfügen</button>
            <span class="ab-hint mt-0">Lücken als ___ (je Unterstrich ca. ein Zeichen)</span>
        </div>
    </div>
</template>

{{-- Ankreuzen --}}
<template x-if="{{ $v }}.typ === 'ankreuzen'">
    <div class="grid gap-2">
        <input type="text" class="ab-input" x-model="{{ $v }}.frage" @input="geaendert()" placeholder="Frage" :disabled="!blatt.darf_bearbeiten" aria-label="Frage">
        <textarea class="ab-textarea" rows="3" :value="({{ $v }}.optionen || []).join('\n')" @input="{{ $v }}.optionen = $event.target.value.split('\n'); geaendert()"
                  placeholder="Eine Antwort pro Zeile" :disabled="!blatt.darf_bearbeiten" aria-label="Antworten"></textarea>
        <label class="ab-check"><input type="checkbox" x-model="{{ $v }}.mehrfach" @change="geaendert()" :disabled="!blatt.darf_bearbeiten"> Mehrere Antworten möglich (eckige Kästchen)</label>
    </div>
</template>

{{-- Tabelle --}}
<template x-if="{{ $v }}.typ === 'tabelle'">
    <div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <template x-for="(zeile, zi) in {{ $v }}.zeilen" :key="zi">
                    <tr>
                        <template x-for="(zelle, ci) in zeile" :key="ci">
                            <td class="p-0.5"><input type="text" class="ab-input ab-input-sm" :class="zi === 0 && {{ $v }}.kopfzeile && 'font-bold'" x-model="{{ $v }}.zeilen[zi][ci]" @input="geaendert()" :disabled="!blatt.darf_bearbeiten" :aria-label="`Zeile ${zi + 1}, Spalte ${ci + 1}`"></td>
                        </template>
                    </tr>
                </template>
            </table>
        </div>
        <div class="flex flex-wrap items-center gap-1 mt-2" x-show="blatt.darf_bearbeiten">
            <button type="button" class="ab-btn ab-btn-ghost ab-btn-sm" @click.stop="tabelle({{ $v }}, 'zeile', 1)"><i class="fas fa-plus"></i> Zeile</button>
            <button type="button" class="ab-btn ab-btn-ghost ab-btn-sm" @click.stop="tabelle({{ $v }}, 'zeile', -1)"><i class="fas fa-minus"></i> Zeile</button>
            <button type="button" class="ab-btn ab-btn-ghost ab-btn-sm" @click.stop="tabelle({{ $v }}, 'spalte', 1)"><i class="fas fa-plus"></i> Spalte</button>
            <button type="button" class="ab-btn ab-btn-ghost ab-btn-sm" @click.stop="tabelle({{ $v }}, 'spalte', -1)"><i class="fas fa-minus"></i> Spalte</button>
            <label class="ab-check ml-2"><input type="checkbox" x-model="{{ $v }}.kopfzeile" @change="geaendert()"> Kopfzeile</label>
        </div>
    </div>
</template>

{{-- Schreiblinien --}}
<template x-if="{{ $v }}.typ === 'schreiblinien'">
    <label class="flex items-center gap-2 text-sm">Anzahl Linien
        <input type="number" min="1" max="30" class="ab-input ab-input-sm w-20" x-model.number="{{ $v }}.anzahl" @input="geaendert()" :disabled="!blatt.darf_bearbeiten">
    </label>
</template>

{{-- Abbildung --}}
<template x-if="{{ $v }}.typ === 'bild'">
    <div class="grid gap-2 sm:grid-cols-[10rem_minmax(0,1fr)] items-start">
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-1 flex items-center justify-center min-h-20">
            <img x-show="{{ $v }}.media_id && blatt.abbildungen[{{ $v }}.media_id]" :src="blatt.abbildungen[{{ $v }}.media_id]" alt="" class="max-h-40 max-w-full">
            <span x-show="!{{ $v }}.media_id || !blatt.abbildungen[{{ $v }}.media_id]" class="text-xs text-slate-400 p-2 text-center">Noch kein Bild</span>
        </div>
        <div class="grid gap-2">
            <input type="text" class="ab-input" x-model="{{ $v }}.beschreibung" @input="geaendert()" placeholder="Kurze Beschreibung (z. B. Kreis, ¾ gefärbt)" maxlength="300" :disabled="!blatt.darf_bearbeiten" aria-label="Beschreibung">
            <div class="flex flex-wrap gap-1" x-show="blatt.darf_bearbeiten">
                <button type="button" class="ab-btn ab-btn-secondary ab-btn-sm" x-show="blatt.originale.length" @click.stop="schneidenStart({{ $v }})"><i class="fas fa-crop-alt"></i> Auf dem Original ausschneiden</button>
                <button type="button" class="ab-btn ab-btn-secondary ab-btn-sm" x-show="{{ $v }}.quelle && !{{ $v }}.media_id && blatt.originale.length" @click.stop="vorschlagSchneiden({{ $v }})"><i class="fas fa-magic"></i> Vorschlag übernehmen</button>
                <label class="ab-btn ab-btn-ghost ab-btn-sm"><i class="fas fa-upload"></i> Bild hochladen
                    <input type="file" accept="image/png,image/jpeg" class="hidden" @change="bildHochladen({{ $v }}, $event)">
                </label>
            </div>
        </div>
    </div>
</template>
