@php
    /** @var \App\Models\Schueler $schueler */
    $statusLabels = [
        'open' => 'Offen',
        'in_progress' => 'In Arbeit',
        'achieved' => 'Erreicht',
        'not_achieved' => 'Nicht erreicht',
        'archived' => 'Archiviert',
    ];
    // Wie DiagnosticService::getRatingText()
    $ratingLabels = ['white' => 'Kann es', 'gray' => 'Aktuelles Ziel', 'dark_gray' => 'Kann es nicht'];
    $entriesByCategory = $entries->groupBy(fn ($e) => $e->category_id ?? 0);
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Dossier {{ $schueler->vorname }} {{ $schueler->nachname }}</title>
    <style>
        @page { margin: 28mm 16mm 22mm 16mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #1f2937; line-height: 1.35; }
        header { position: fixed; top: -21mm; left: 0; right: 0; height: 16mm; border-bottom: 1px solid #d1d5db; }
        header img { height: 12mm; float: left; margin-right: 4mm; }
        header .school { font-size: 11pt; font-weight: bold; padding-top: 1mm; }
        header .sub { font-size: 8pt; color: #6b7280; }
        footer { position: fixed; bottom: -14mm; left: 0; right: 0; height: 8mm; font-size: 7pt; color: #6b7280; border-top: 1px solid #d1d5db; padding-top: 1.5mm; }
        h1 { font-size: 15pt; margin: 0 0 1mm 0; }
        h2 { font-size: 11.5pt; margin: 6mm 0 2mm 0; padding-bottom: 1mm; border-bottom: 2px solid #1E40AF; color: #1E40AF; }
        h3 { font-size: 10pt; margin: 3mm 0 1.5mm 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; vertical-align: top; padding: 1.2mm 1.5mm; border-bottom: 1px solid #e5e7eb; }
        th { background: #f3f4f6; font-size: 8.5pt; }
        .meta td { border: none; padding: 0.5mm 0; }
        .meta td.label { color: #6b7280; width: 32mm; }
        .swatch { display: inline-block; width: 3mm; height: 3mm; border-radius: 1mm; margin-right: 1.5mm; }
        .badge { display: inline-block; padding: 0.3mm 1.5mm; border-radius: 1mm; font-size: 7.5pt; }
        .badge-confidential { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .muted { color: #6b7280; }
        .entry-date { white-space: nowrap; width: 20mm; }
        .empty { color: #9ca3af; font-style: italic; }
    </style>
</head>
<body>
<header>
    @if($logo)
        <img src="{{ $logo }}" alt="Logo">
    @endif
    <div class="school">{{ $schoolName }}</div>
    <div class="sub">Pädagogisches Dossier</div>
</header>

<footer>
    Vertraulich – nur für den dienstlichen Gebrauch
</footer>

<main>
    <h1>{{ $schueler->vorname }} {{ $schueler->nachname }}</h1>
    <table class="meta">
        <tr><td class="label">Klasse</td><td>{{ $schueler->klasse?->name ?? '—' }}</td></tr>
        <tr><td class="label">Zeitraum</td><td>{{ $from->format('d.m.Y') }} – {{ $to->format('d.m.Y') }}</td></tr>
        <tr><td class="label">Erstellt am</td><td>{{ $meta['generated_at']->format('d.m.Y H:i') }} Uhr</td></tr>
        <tr><td class="label">Erstellt von</td><td>{{ $meta['generated_by'] }}</td></tr>
    </table>

    {{-- ── Tagebuch ─────────────────────────────────────────────── --}}
    <h2>Pädagogisches Tagebuch ({{ $entries->count() }} {{ $entries->count() === 1 ? 'Eintrag' : 'Einträge' }})</h2>

    @forelse($entriesByCategory as $categoryEntries)
        @php $category = $categoryEntries->first()->category; @endphp
        <h3>
            <span class="swatch" style="background: {{ $category?->color ?: '#9ca3af' }};"></span>
            {{ $category?->name ?? 'Ohne Kategorie' }} ({{ $categoryEntries->count() }})
        </h3>
        <table>
            @foreach($categoryEntries as $entry)
                <tr>
                    <td class="entry-date">{{ $entry->datum?->format('d.m.Y') }}</td>
                    <td>
                        @if($entry->dossier_only)
                            <span class="badge badge-confidential">Vertraulich</span>
                        @endif
                        {!! nl2br(e($entry->content)) !!}
                        <div class="muted">{{ $entry->user?->name }}</div>
                    </td>
                </tr>
            @endforeach
        </table>
    @empty
        <p class="empty">Keine Einträge im Zeitraum.</p>
    @endforelse

    @if($diary_goals->isNotEmpty())
        <h3>Ziele aus dem Tagebuch</h3>
        <table>
            <tr><th>Ziel</th><th>Angelegt</th><th>Erreicht</th></tr>
            @foreach($diary_goals as $goal)
                <tr>
                    <td>{{ $goal['goal_text'] }}</td>
                    <td class="entry-date">{{ $goal['created_at'] ? \Carbon\Carbon::parse($goal['created_at'])->format('d.m.Y') : '—' }}</td>
                    <td class="entry-date">{{ $goal['achieved_at'] ? \Carbon\Carbon::parse($goal['achieved_at'])->format('d.m.Y') : '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    {{-- ── Graduierung ──────────────────────────────────────────── --}}
    <h2>Graduierung</h2>
    <table class="meta">
        <tr>
            <td class="label">Aktuelle Stufe</td>
            <td>
                @if($grading['current_stage'])
                    <strong>{{ $grading['current_stage']['title'] }}</strong>
                    @if($grading['current_stage']['achieved_at'])
                        <span class="muted">(seit {{ \Carbon\Carbon::parse($grading['current_stage']['achieved_at'])->format('d.m.Y') }})</span>
                    @endif
                @else
                    <span class="empty">Keine Stufe vergeben</span>
                @endif
            </td>
        </tr>
    </table>

    @if($grading['stage_history']->isNotEmpty())
        <h3>Verlauf im Zeitraum</h3>
        <table>
            <tr><th>Datum</th><th>Von</th><th>Auf</th><th>Durch</th></tr>
            @foreach($grading['stage_history'] as $h)
                <tr>
                    <td class="entry-date">{{ $h['changed_at'] ? \Carbon\Carbon::parse($h['changed_at'])->format('d.m.Y') : '—' }}</td>
                    <td>{{ $h['previous_stage_title'] ?? '—' }}</td>
                    <td>{{ $h['stage_title'] ?? '—' }}</td>
                    <td>{{ $h['changed_by_name'] ?? '—' }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="empty">Keine Stufenwechsel im Zeitraum.</p>
    @endif

    {{-- ── Diagnose (nur mit "view diagnostics") ────────────────── --}}
    @if($diagnostic)
        <h2>Diagnose</h2>

        @if($diagnostic['sessions']->isNotEmpty())
            <h3>Sitzungen</h3>
            <table>
                <tr><th>Datum</th><th>Bereich</th><th>{{ $ratingLabels['white'] }}</th><th>{{ $ratingLabels['gray'] }}</th><th>{{ $ratingLabels['dark_gray'] }}</th><th>Notiz</th></tr>
                @foreach($diagnostic['sessions'] as $session)
                    <tr>
                        <td class="entry-date">{{ $session->session_date?->format('d.m.Y') }}</td>
                        <td>{{ $session->area?->name }}</td>
                        <td>{{ $session->assessments->where('rating', 'white')->count() }}</td>
                        <td>{{ $session->assessments->where('rating', 'gray')->count() }}</td>
                        <td>{{ $session->assessments->where('rating', 'dark_gray')->count() }}</td>
                        <td>{{ $session->notes }}</td>
                    </tr>
                @endforeach
            </table>
        @else
            <p class="empty">Keine Diagnosesitzungen im Zeitraum.</p>
        @endif

        <h3>Entwicklungsziele</h3>
        @if($diagnostic['development_goals']->isNotEmpty())
            <table>
                <tr><th>Ziel</th><th>Bereich</th><th>Zieldatum</th><th>Status</th></tr>
                @foreach($diagnostic['development_goals'] as $goal)
                    <tr>
                        <td>
                            {{ $goal->title }}
                            @if($goal->completion_notes)
                                <div class="muted">{{ $goal->completion_notes }}</div>
                            @endif
                        </td>
                        <td>{{ $goal->area?->name ?? '—' }}</td>
                        <td class="entry-date">{{ $goal->target_date?->format('d.m.Y') ?? '—' }}</td>
                        <td>{{ $statusLabels[$goal->status] ?? $goal->status }}</td>
                    </tr>
                @endforeach
            </table>
        @else
            <p class="empty">Keine Entwicklungsziele.</p>
        @endif
    @endif
</main>
</body>
</html>
