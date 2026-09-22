{{--
    Partial: VZÄ-Trend-Chart
    ─────────────────────────────────────────────────────────────────────
    Erwartet folgende Variablen (per @include ... mit [...]):

    @param array        $chartLabels     Monats-Labels  ['Jan 24', 'Feb 24', ...]
    @param array        $chartVzSp1      VZÄ Verein (SP1) pro Monat  [5.69, 5.64, ...]
    @param array        $chartVzGesetzl  VZÄ Gesetzl. Minimum        [5.40, 5.40, ...]
    @param array        $chartBudget     Budget-Rest in Stunden       [+18.3, -2.1, ...]
    @param array        $chartVzIst      VZÄ Ist (aus Zeiterfassung)  [5.12, null, ...]
    @param string|null  $chartTitel      Überschrift des Charts

    ─────────────────────────────────────────────────────────────────────
    CSS: Innerhalb .hort-planung-wrapper (Tailwind v4 scoped)
    JS:  Chart.js wird via CDN einmalig geladen (geprüft ob bereits vorhanden)
--}}

@php
    // Eindeutige Chart-ID für mehrere Instanzen auf einer Seite
    $chartId     = 'hortTrendChart_' . uniqid();
    $chartTitel  = $chartTitel ?? 'VZÄ-Verlauf';
    $hasIstData  = collect($chartVzIst ?? [])->filter(fn($v) => $v !== null)->isNotEmpty();
    $hasBudget   = collect($chartBudget ?? [])->filter(fn($v) => $v !== null)->isNotEmpty();

    // JSON-Daten für Chart.js
    $jsLabels    = json_encode($chartLabels   ?? []);
    $jsVzSp1     = json_encode($chartVzSp1    ?? []);
    $jsVzGesetzl = json_encode($chartVzGesetzl ?? []);
    $jsBudget    = json_encode($chartBudget   ?? []);
    $jsVzIst     = json_encode($chartVzIst    ?? []);
@endphp

{{-- ── Chart-Wrapper ──────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mt-6">

    {{-- Header --}}
    <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
            </svg>
            {{ $chartTitel }}
        </h2>

        {{-- Legende --}}
        <div class="hidden sm:flex items-center gap-4 text-[11px] text-gray-500">
            <span class="flex items-center gap-1.5">
                <span class="inline-block w-5 h-0.5 bg-blue-500 rounded"></span>
                VZÄ Verein (Soll)
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block w-5 border-t-2 border-dashed border-red-400 rounded"></span>
                Gesetzl. Minimum
            </span>
            @if($hasIstData)
            <span class="flex items-center gap-1.5">
                <span class="inline-block w-5 border-t-2 border-dashed border-emerald-500 rounded"></span>
                VZÄ Ist
            </span>
            @endif
            @if($hasBudget)
            <span class="flex items-center gap-1.5">
                <span class="inline-block w-3 h-3 rounded-sm bg-emerald-300/70 border border-emerald-400"></span>
                Budget-Rest (Std., rechte Achse)
            </span>
            @endif
        </div>
    </div>

    {{-- Canvas --}}
    <div class="p-4" style="position: relative; height: 280px;">
        <canvas id="{{ $chartId }}" aria-label="{{ $chartTitel }}" role="img"></canvas>
    </div>

    {{-- Keine Daten Hinweis --}}
    @if(empty($chartLabels))
    <div class="px-5 pb-5 text-center text-sm text-gray-400">
        Keine Daten für den Chart vorhanden.
    </div>
    @endif
</div>

{{-- ── Chart.js laden (einmalig, idempotent) ──────────────────────────────── --}}
@once
@push('js')
<script>
(function() {
    if (window.Chart) { return; } // bereits geladen
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';
    s.onload = function() { window.dispatchEvent(new Event('chartjs:ready')); };
    document.head.appendChild(s);
})();
</script>
@endpush
@endonce

{{-- ── Chart-Initialisierung ───────────────────────────────────────────────── --}}
@push('js')
<script>
(function() {
    var chartId     = '{{ $chartId }}';
    var labels      = {!! $jsLabels !!};
    var vzSp1       = {!! $jsVzSp1 !!};
    var vzGesetzl   = {!! $jsVzGesetzl !!};
    var budget      = {!! $jsBudget !!};
    var vzIst       = {!! $jsVzIst !!};
    var hasIst      = {{ $hasIstData ? 'true' : 'false' }};
    var hasBudget   = {{ $hasBudget ? 'true' : 'false' }};

    function initChart() {
        var canvas = document.getElementById(chartId);
        if (!canvas) return;

        // Bestehende Chart-Instanz zerstören, bevor der Canvas wiederverwendet wird
        var existingChart = Chart.getChart(canvas);
        if (existingChart) {
            existingChart.destroy();
        }

        // Datasets zusammenbauen
        var datasets = [];

        // ── VZÄ Verein (SP1) – durchgezogene blaue Linie ──
        datasets.push({
            label:           'VZÄ Verein (Soll)',
            data:            vzSp1,
            yAxisID:         'yVz',
            type:            'line',
            borderColor:     '#3b82f6',
            backgroundColor: 'rgba(59,130,246,0.08)',
            borderWidth:     2.5,
            pointRadius:     3,
            pointHoverRadius: 5,
            fill:            false,
            tension:         0.3,
        });

        // ── VZÄ Gesetzliches Minimum – gepunktete rote Linie ──
        datasets.push({
            label:           'Gesetzl. Minimum',
            data:            vzGesetzl,
            yAxisID:         'yVz',
            type:            'line',
            borderColor:     '#ef4444',
            backgroundColor: 'transparent',
            borderWidth:     2,
            borderDash:      [5, 4],
            pointRadius:     0,
            fill:            false,
            tension:         0.2,
        });

        // ── VZÄ Ist (aus Zeiterfassung) – gestrichelte grüne Linie ──
        if (hasIst) {
            datasets.push({
                label:           'VZÄ Ist',
                data:            vzIst,
                yAxisID:         'yVz',
                type:            'line',
                borderColor:     '#10b981',
                backgroundColor: 'transparent',
                borderWidth:     2,
                borderDash:      [6, 3],
                pointRadius:     3,
                pointHoverRadius: 5,
                fill:            false,
                tension:         0.3,
                spanGaps:        false,
            });
        }

        // ── Budget-Rest in Stunden – Balken (sekundäre Y-Achse) ──
        if (hasBudget) {
            datasets.push({
                label:           'Budget-Rest (Stunden)',
                data:            budget,
                yAxisID:         'yStunden',
                type:            'bar',
                backgroundColor: budget.map(function(v) {
                    return v === null ? 'transparent'
                         : v >= 0    ? 'rgba(16,185,129,0.30)'
                                     : 'rgba(239,68,68,0.30)';
                }),
                borderColor: budget.map(function(v) {
                    return v === null ? 'transparent'
                         : v >= 0    ? 'rgba(16,185,129,0.80)'
                                     : 'rgba(239,68,68,0.80)';
                }),
                borderWidth: 1,
                borderRadius: 3,
                order: 2,
            });
        }

        // ── Scales-Konfiguration ──────────────────────────────────────────────
        var scales = {
            x: {
                grid: {
                    color:     'rgba(0,0,0,0.04)',
                    drawBorder: false,
                },
                ticks: {
                    font:  { size: 10 },
                    color: '#9ca3af',
                    maxRotation: 45,
                },
            },
            yVz: {
                type:     'linear',
                position: 'left',
                title: {
                    display: true,
                    text:    'VZÄ',
                    font:    { size: 11 },
                    color:   '#6b7280',
                },
                grid: {
                    color:     'rgba(0,0,0,0.05)',
                    drawBorder: false,
                },
                ticks: {
                    font:  { size: 10 },
                    color: '#9ca3af',
                    callback: function(val) {
                        return val.toLocaleString('de-DE', {
                            minimumFractionDigits: 1,
                            maximumFractionDigits: 2,
                        });
                    },
                },
                beginAtZero: false,
            },
        };

        // Sekundäre Achse für Budget-Rest in Stunden (nur wenn Daten vorhanden)
        if (hasBudget) {
            scales.yStunden = {
                type:     'linear',
                position: 'right',
                title: {
                    display: true,
                    text:    'Stunden',
                    font:    { size: 11 },
                    color:   '#6b7280',
                },
                grid: {
                    drawOnChartArea: false, // Kein zweites Gitter
                },
                ticks: {
                    font:  { size: 10 },
                    color: '#9ca3af',
                    callback: function(val) {
                        return (val >= 0 ? '+' : '') +
                               val.toLocaleString('de-DE', { maximumFractionDigits: 0 });
                    },
                },
            };
        }

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels:   labels,
                datasets: datasets,
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                interaction: {
                    mode:      'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display:  true,
                        position: 'bottom',
                        labels: {
                            boxWidth:  14,
                            font:      { size: 11 },
                            color:     '#6b7280',
                            padding:   16,
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var val = ctx.parsed.y;
                                if (val === null || val === undefined) return null;
                                var suffix = (ctx.dataset.yAxisID === 'yStunden') ? ' h' : ' VZÄ';
                                var formatted = val.toLocaleString('de-DE', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 3,
                                });
                                return ctx.dataset.label + ': ' + formatted + suffix;
                            },
                        },
                    },
                },
                scales: scales,
            },
        });
    }

    // Warten bis Chart.js geladen ist
    if (window.Chart) {
        initChart();
    } else {
        window.addEventListener('chartjs:ready', initChart, { once: true });
        // Fallback: DOM-Ready Polling
        document.addEventListener('DOMContentLoaded', function() {
            if (window.Chart) { initChart(); }
        });
    }
})();
</script>
@endpush

