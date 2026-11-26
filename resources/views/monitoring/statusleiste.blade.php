{{--
    Systemstatus-Leiste oberhalb der Dashboard-Karten (Dashboard v1 + v2).
    Daten: MonitoringStatusComposer. Sichtbar nur für die Rollen aus Einstellungen → Monitoring.
    Aktualisiert sich jede Minute über monitoring.statusleiste (liefert nur die innere Leiste).
    Eigenes, schlichtes CSS (mon-*), damit es unter Bootstrap und Tailwind gleich aussieht.
--}}
@if($monSichtbar)
    <div class="mon-slot" data-mon-url="{{ route('monitoring.statusleiste') }}">
        @include('monitoring._leiste', ['monAnzeige' => $monAnzeige, 'monVerwalten' => $monVerwalten])
    </div>

    @once
        <style>
            .mon-slot { margin: .5rem 0 .75rem; }
            .mon-leiste {
                display: flex; flex-wrap: wrap; align-items: center; gap: .375rem .5rem;
                padding: .5rem .75rem; border: 1px solid #e5e7eb; border-radius: .75rem;
                background: #fff; font-size: .8125rem; line-height: 1.3; color: #374151;
                box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
            }
            .mon-leiste--stoerung { background: #fef2f2; border-color: #fecaca; }
            .mon-titel { display: inline-flex; align-items: center; gap: .375rem; font-weight: 600; color: #111827; margin-right: .25rem; }
            .mon-leiste--stoerung .mon-titel { color: #991b1b; }
            .mon-pill {
                display: inline-flex; align-items: center; gap: .375rem;
                padding: .1875rem .625rem; border-radius: 999px; border: 1px solid #e5e7eb;
                background: #f9fafb; white-space: nowrap; cursor: default;
            }
            .mon-punkt { width: .5rem; height: .5rem; border-radius: 50%; background: #9ca3af; flex: none; }
            .mon-pill--ok .mon-punkt { background: #16a34a; }
            .mon-pill--fehler { background: #fee2e2; border-color: #fca5a5; color: #991b1b; font-weight: 600; }
            .mon-pill--fehler .mon-punkt { background: #dc2626; box-shadow: 0 0 0 3px rgba(220, 38, 38, .2); }
            .mon-pill--betroffen { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
            .mon-pill--betroffen .mon-punkt { background: #d97706; }
            .mon-pill--unbekannt { color: #6b7280; }
            .mon-zusatz { font-weight: 400; opacity: .85; }
            .mon-stand { margin-left: auto; color: #6b7280; font-size: .75rem; white-space: nowrap; }
            .mon-link { color: #6b7280 !important; text-decoration: none !important; padding: 0 .25rem; }
            .mon-link:hover { color: #111827 !important; }
            .mon-leer { color: #6b7280; }
            .mon-sr { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        </style>
        <script>
            (function () {
                function aktualisieren() {
                    document.querySelectorAll('.mon-slot[data-mon-url]').forEach(function (slot) {
                        fetch(slot.dataset.monUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                            .then(function (r) {
                                if (r.status === 204) { slot.remove(); return null; }
                                return r.ok ? r.text() : null;
                            })
                            .then(function (html) { if (html) { slot.innerHTML = html; } })
                            .catch(function () {});
                    });
                }
                setInterval(function () { if (!document.hidden) { aktualisieren(); } }, 60000);
            })();
        </script>
    @endonce
@endif
