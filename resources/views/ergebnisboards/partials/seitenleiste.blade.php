{{-- Seitenleiste „Kommentare & Verlauf“ des ganzen Boards (dialog === 'seitenleiste') --}}
<div class="erg-backdrop is-seitlich" x-show.important="dialog === 'seitenleiste'" x-cloak @click.self="dialogSchliessen()">
    <aside class="erg-seitenleiste" data-dialog="seitenleiste" role="dialog" aria-modal="true" aria-labelledby="erg-seite-titel"
           @keydown.tab="fokusFalle($event)">
        <div class="erg-dialog-kopf">
            <div class="min-w-0">
                <h2 id="erg-seite-titel" class="erg-dialog-titel">Kommentare &amp; Verlauf</h2>
                <p class="text-xs text-slate-500 truncate" x-text="board.titel"></p>
            </div>
            <button type="button" class="erg-icon-btn" @click="dialogSchliessen()" aria-label="Seitenleiste schließen"><i class="fas fa-times"></i></button>
        </div>

        <div class="erg-reiter" role="tablist">
            <button type="button" role="tab" :aria-selected="(seitenReiter === 'kommentare').toString()"
                    :class="{ 'is-active': seitenReiter === 'kommentare' }" @click="seitenReiterWaehlen('kommentare')">
                <i class="far fa-comments"></i> Kommentare
                <span class="erg-zaehler" x-show.important="board.kommentare_board" x-text="board.kommentare_board"></span>
            </button>
            <button type="button" role="tab" :aria-selected="(seitenReiter === 'verlauf').toString()"
                    :class="{ 'is-active': seitenReiter === 'verlauf' }" @click="seitenReiterWaehlen('verlauf')">
                <i class="fas fa-history"></i> Verlauf
            </button>
        </div>

        <div class="erg-dialog-body flex-1">
            <div x-show.important="seitenReiter === 'kommentare'">
                <p class="erg-hint mt-0 mb-3">Kommentare zum Board als Ganzes. Kommentare zu einer Karte stehen in der Karte selbst.</p>
                @include('ergebnisboards.partials.kommentare', ['ziel' => 'board'])
            </div>
            <div x-show.important="seitenReiter === 'verlauf'">
                @include('ergebnisboards.partials.verlauf', ['ziel' => 'board'])
            </div>
        </div>
    </aside>
</div>
