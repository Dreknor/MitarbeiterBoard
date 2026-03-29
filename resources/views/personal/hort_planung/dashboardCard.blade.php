{{-- Dashboard-Card: Hortstunden-Planung (§6.7) --}}
@can('view hort planung')
<div class="card" id="card_{{ $card->id ?? 'hort_planung' }}">
    <div class="card-header text-white bg-gradient-directional-blue d-flex align-items-center justify-content-between">
        <h5 class="mb-0">
            <i class="fas fa-child mr-2"></i>
            {{ $card->title ?? 'Hortstunden-Planung' }}
        </h5>
        @if($hortPlanung)
            <a href="{{ route('hort-planung.show', $hortPlanung) }}"
               class="btn btn-sm btn-outline-light ml-auto">
                <i class="fas fa-table"></i>
            </a>
        @endif
    </div>

    <div class="card-body p-3">
        @if(!$hortPlanung)
            {{-- Keine aktive Planung ──────────────────────────────────── --}}
            <p class="text-muted small mb-2">
                <i class="fas fa-info-circle text-secondary mr-1"></i>
                Keine aktive Hortstunden-Planung gefunden.
            </p>
            @can('manage hort planung')
                <a href="{{ route('hort-planung.create') }}"
                   class="btn btn-sm btn-primary">
                    <i class="fas fa-plus mr-1"></i> Neue Planung
                </a>
            @endcan
        @else
            {{-- Planungsname & Zeitraum ────────────────────────────────── --}}
            <div class="d-flex align-items-center mb-2">
                <span class="badge badge-primary mr-2">aktiv</span>
                <strong class="text-dark">{{ $hortPlanung->name }}</strong>
            </div>
            <small class="text-muted d-block mb-3">
                {{ $hortPlanung->start_monat->format('M Y') }}
                –
                {{ $hortPlanung->end_monat->format('M Y') }}
                &middot; {{ $hortPlanung->department->name ?? '–' }}
            </small>

            @if($hortAktuellerMonat)
                {{-- Aktueller Monat – Kennzahlen ─────────────────────── --}}
                <div class="row no-gutters text-center mb-2">
                    {{-- VZÄ SP1 --}}
                    <div class="col-4 border-right">
                        <div class="text-xs text-muted">VZÄ (SP1)</div>
                        <div class="font-weight-bold text-dark">
                            {{ number_format($hortAktuellerMonat['summe_vz_sp1'], 2, ',', '.') }}
                        </div>
                    </div>
                    {{-- VZÄ gesetzl. --}}
                    <div class="col-4 border-right">
                        <div class="text-xs text-muted">gesetzl. Min.</div>
                        <div class="font-weight-bold text-dark">
                            {{ number_format($hortAktuellerMonat['summe_gesetz_vz'], 2, ',', '.') }}
                        </div>
                    </div>
                    {{-- Budget-Rest --}}
                    <div class="col-4">
                        <div class="text-xs text-muted">Budget-Rest</div>
                        <div class="font-weight-bold
                            {{ ($hortBudgetRest ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($hortBudgetRest ?? 0, 1, ',', '.') }}h
                        </div>
                    </div>
                </div>

                {{-- Abwesenheits-Warnung ────────────────────────────── --}}
                @if($hortAbwesenheiten->isNotEmpty())
                    <div class="alert alert-warning py-1 px-2 mb-2 small">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        {{ $hortAbwesenheiten->count() }}
                        {{ $hortAbwesenheiten->count() === 1 ? 'Langzeitabwesenheit' : 'Langzeitabwesenheiten' }}
                        im Planungszeitraum
                    </div>
                @endif

                {{-- Budget-Rest-Anzeige farbig ──────────────────────── --}}
                @if(($hortBudgetRest ?? 0) < 0)
                    <div class="alert alert-danger py-1 px-2 mb-2 small">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        Überplanung: {{ number_format(abs($hortBudgetRest), 1, ',', '.') }}h zu viel verplant
                    </div>
                @endif
            @endif

            {{-- Aktionen ─────────────────────────────────────────────── --}}
            <div class="d-flex gap-1 mt-2">
                <a href="{{ route('hort-planung.show', $hortPlanung) }}"
                   class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-table mr-1"></i> Matrix
                </a>
                <a href="{{ route('hort-planung.rueckblick', $hortPlanung) }}"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-history mr-1"></i> Rückblick
                </a>
                @can('manage hort planung')
                    <a href="{{ route('hort-planung.export', $hortPlanung) }}"
                       class="btn btn-sm btn-outline-success ml-auto">
                        <i class="fas fa-file-excel mr-1"></i> Export
                    </a>
                @endcan
            </div>
        @endif
    </div>
</div>
@endcan

