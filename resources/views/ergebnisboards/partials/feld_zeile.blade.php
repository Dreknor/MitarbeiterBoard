{{-- Eine Zeile im Felder-Dialog. $f = Alpine-Variable, $liste = Array-Ausdruck, $index = Index-Variable, $unter = Unterbereich? --}}
<div class="erg-feld-zeile {{ $unter ? 'is-unter' : '' }}" :class="'erg-farbe-' + {{ $f }}.farbe">
    <div class="flex flex-wrap items-center gap-2">
        <span class="erg-punkt"></span>
        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $unter ? 'Unterbereich' : 'Feld' }}</span>
        <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-100 rounded px-1.5" x-show.important="{{ $f }}.neu">neu</span>
        <span class="text-xs text-slate-400" x-show.important="!{{ $f }}.neu" x-text="anzahl({{ $f }}.schluessel) + ' Karten'"></span>
        <div class="ml-auto flex items-center gap-0.5">
            @if($unter)
                <button type="button" class="erg-icon-btn" @click="verschieben({{ $liste }}, {{ $index }}, -1)" :disabled="{{ $index }} === 0" aria-label="Nach oben"><i class="fas fa-arrow-up"></i></button>
                <button type="button" class="erg-icon-btn" @click="verschieben({{ $liste }}, {{ $index }}, 1)" :disabled="{{ $index }} === {{ $liste }}.length - 1" aria-label="Nach unten"><i class="fas fa-arrow-down"></i></button>
            @else
                <template x-if="board.bereiche_frei">
                    <span class="inline-flex">
                        <button type="button" class="erg-icon-btn" @click="verschieben({{ $liste }}, {{ $index }}, -1)" :disabled="{{ $index }} === 0" aria-label="Nach links/oben"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" class="erg-icon-btn" @click="verschieben({{ $liste }}, {{ $index }}, 1)" :disabled="{{ $index }} === {{ $liste }}.length - 1" aria-label="Nach rechts/unten"><i class="fas fa-arrow-down"></i></button>
                    </span>
                </template>
            @endif
            <button type="button" class="erg-icon-btn hover:text-red-600" @click="{{ $liste }}.splice({{ $index }}, 1)"
                    :disabled="!feldEntfernbar({{ $f }}, {{ $unter ? 'true' : 'false' }})"
                    :title="entfernenHinweis({{ $f }}, {{ $unter ? 'true' : 'false' }})" :aria-label="entfernenHinweis({{ $f }}, {{ $unter ? 'true' : 'false' }})">
                <i class="far fa-trash-alt"></i>
            </button>
        </div>
    </div>
    <div class="grid gap-2 sm:grid-cols-3">
        <div>
            <label class="erg-label text-xs mb-1">Überschrift</label>
            <input type="text" class="erg-input erg-input-sm" x-model="{{ $f }}.titel" maxlength="80">
        </div>
        <div>
            <label class="erg-label text-xs mb-1">Badge</label>
            <input type="text" class="erg-input erg-input-sm" x-model="{{ $f }}.badge" maxlength="60" placeholder="z. B. Sofort umsetzen">
        </div>
        <div>
            <label class="erg-label text-xs mb-1">Hinweis</label>
            <input type="text" class="erg-input erg-input-sm" x-model="{{ $f }}.hinweis" maxlength="200">
        </div>
    </div>
    <div class="flex flex-wrap items-end gap-4">
        <div>
            <span class="erg-label text-xs mb-1">Farbe</span>
            <div class="erg-farbwahl" role="radiogroup" aria-label="Farbe">
                <template x-for="(label, farbe) in farben" :key="farbe">
                    <button type="button" :class="['erg-farbe-' + farbe, { 'is-active': {{ $f }}.farbe === farbe }]" role="radio"
                            :aria-checked="({{ $f }}.farbe === farbe).toString()" :title="label" :aria-label="label"
                            @click="{{ $f }}.farbe = farbe"></button>
                </template>
            </div>
        </div>
        <div>
            <label class="erg-label text-xs mb-1">Karten</label>
            <select class="erg-select erg-input-sm" x-model.number="{{ $f }}.raster">
                <option value="1" :selected="{{ $f }}.raster == 1">untereinander</option>
                <option value="2" :selected="{{ $f }}.raster == 2">zweispaltig</option>
            </select>
        </div>
        @unless($unter)
            <template x-if="board.bereiche_frei">
                <div>
                    <label class="erg-label text-xs mb-1">Status (Aufgaben)</label>
                    <select class="erg-select erg-input-sm" x-model="{{ $f }}.status">
                        <option value="" :selected="!{{ $f }}.status">–</option>
                        <template x-for="(label, wert) in statusLabel" :key="wert">
                            <option :value="wert" x-text="label" :selected="{{ $f }}.status === wert"></option>
                        </template>
                    </select>
                </div>
            </template>
        @endunless
    </div>
</div>
