{{-- PIN-Pad (ohne Framework, damit das Terminal minimal bleibt) --}}
<div id="pinpad" class="mx-auto" style="max-width: 22rem;">
    <div class="flex items-center justify-center gap-3 mb-6" id="pin-dots" aria-live="polite">
        @for($i = 0; $i < 6; $i++)<span class="zw-pin-dot"></span>@endfor
    </div>
    <div class="grid grid-cols-3 gap-3">
        @foreach([1, 2, 3, 4, 5, 6, 7, 8, 9] as $ziffer)
            <button type="button" class="zw-pin-key" data-ziffer="{{ $ziffer }}">{{ $ziffer }}</button>
        @endforeach
        <button type="button" class="zw-pin-key" data-aktion="loeschen" aria-label="Letzte Ziffer löschen" style="font-size:1.5rem;"><i class="fas fa-backspace"></i></button>
        <button type="button" class="zw-pin-key" data-ziffer="0">0</button>
        <button type="button" class="zw-pin-key" data-aktion="ok" aria-label="Bestätigen" style="background: rgba(16,185,129,.9); font-size:1.5rem;"><i class="fas fa-check"></i></button>
    </div>
</div>

@push('js')
    <script>
        (function () {
            const felder = @json($felder ?? ['secret_key']);
            const formular = document.getElementById('pinForm');
            const punkte = document.getElementById('pin-dots');
            let aktiv = 0;
            const werte = felder.map(() => '');
            const hinweis = document.getElementById('pin-hinweis');
            const texte = @json($texte ?? ['Bitte PIN eingeben']);

            function zeichnen() {
                const laenge = Math.max(6, werte[aktiv].length);
                punkte.innerHTML = '';
                for (let i = 0; i < laenge; i++) {
                    const p = document.createElement('span');
                    p.className = 'zw-pin-dot' + (i < werte[aktiv].length ? ' is-filled' : '');
                    punkte.appendChild(p);
                }
                if (hinweis) hinweis.textContent = texte[aktiv] || '';
            }
            function absenden() {
                if (werte[aktiv].length < 6) { punkte.animate([{ transform: 'translateX(-6px)' }, { transform: 'translateX(6px)' }, { transform: 'translateX(0)' }], 200); return; }
                if (aktiv < felder.length - 1) { aktiv++; zeichnen(); return; }
                felder.forEach((name, i) => { formular.querySelector(`[name="${name}"]`).value = werte[i]; });
                formular.submit();
            }
            document.querySelectorAll('#pinpad [data-ziffer]').forEach((b) => b.addEventListener('click', () => {
                if (werte[aktiv].length < 10) { werte[aktiv] += b.dataset.ziffer; zeichnen(); }
            }));
            document.querySelector('#pinpad [data-aktion="loeschen"]').addEventListener('click', () => { werte[aktiv] = werte[aktiv].slice(0, -1); zeichnen(); });
            document.querySelector('#pinpad [data-aktion="ok"]').addEventListener('click', absenden);
            document.addEventListener('keydown', (e) => {
                if (/^\d$/.test(e.key) && werte[aktiv].length < 10) { werte[aktiv] += e.key; zeichnen(); }
                else if (e.key === 'Backspace') { werte[aktiv] = werte[aktiv].slice(0, -1); zeichnen(); }
                else if (e.key === 'Enter') { e.preventDefault(); absenden(); }
            });
            zeichnen();
            // Nach 60 Sekunden ohne Abschluss zurück zum Start
            setTimeout(() => { window.location.href = @json(route('time_recording.logout')); }, 60000);
        })();
    </script>
@endpush
