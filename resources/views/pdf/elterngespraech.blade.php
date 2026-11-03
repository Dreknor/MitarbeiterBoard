@php
    /** @var \App\Models\PaedDiaryElterngespraech $g */
    $verantwortlich = \App\Models\PaedDiaryElterngespraechVereinbarung::VERANTWORTLICH;
    $nr = fn (string $abschnitt) => $abschnitte[$abschnitt] ?? '';
    $schule = $g->teilnehmer->pluck('name')->prepend($g->user?->name)->filter()->unique()->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Protokoll Elterngespräch {{ $schueler?->vorname }} {{ $schueler?->nachname }}</title>
    <style>
        @page { margin: 18mm 18mm 24mm 20mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #1f2937; line-height: 1.4; }
        footer { position: fixed; bottom: -16mm; left: 0; right: 0; height: 9mm; font-size: 7pt; color: #6b7280; border-top: 0.6pt solid #d1d5db; padding-top: 1.8mm; }

        .kopf { width: 100%; border-collapse: collapse; margin-bottom: 7mm; }
        .kopf td { vertical-align: top; padding: 0; }
        .kopf img { height: 18mm; }
        .kopf .schule { text-align: right; font-size: 8pt; color: #4b5563; line-height: 1.45; }
        .kopf .schule b { display: block; font-size: 11pt; color: #111827; margin-bottom: 0.8mm; }
        .kopf-linie { border: 0; border-top: 1.4pt solid #1E40AF; margin: 0 0 8mm 0; }

        .titel { font-size: 17pt; font-weight: bold; color: #111827; margin: 0; letter-spacing: 0.2pt; }
        .untertitel { font-size: 10pt; color: #4b5563; margin: 1mm 0 6mm 0; }
        .hinweis { border: 0.8pt solid #f59e0b; background: #fffbeb; color: #92400e; padding: 2mm 3mm; margin-bottom: 5mm; font-size: 8.5pt; }

        .eckdaten { width: 100%; border-collapse: collapse; background: #f8fafc; border: 0.6pt solid #e2e8f0; margin-bottom: 7mm; }
        .eckdaten td { padding: 1.6mm 3mm; vertical-align: top; border-bottom: 0.6pt solid #eef2f6; }
        .eckdaten tr:last-child td { border-bottom: 0; }
        .eckdaten .label { width: 38mm; color: #64748b; font-size: 8.5pt; }

        h2 { font-size: 10.5pt; color: #1E40AF; margin: 6mm 0 2.5mm 0; padding-bottom: 1.2mm; border-bottom: 0.8pt solid #c7d2fe; page-break-after: avoid; }
        h2 .nr { margin-right: 1.5mm; }
        p { margin: 0 0 2mm 0; }
        .text { white-space: pre-line; }

        table.liste { width: 100%; border-collapse: collapse; }
        table.liste th { text-align: left; font-size: 8pt; color: #475569; font-weight: bold; background: #f1f5f9; padding: 1.6mm 2mm; border-bottom: 0.8pt solid #cbd5e1; }
        table.liste td { padding: 1.8mm 2mm; vertical-align: top; border-bottom: 0.6pt solid #e5e7eb; }
        table.liste tr:nth-child(even) td { background: #fafbfc; }
        .nr-spalte { width: 7mm; color: #64748b; }
        .schmal { width: 30mm; white-space: nowrap; }
        .muted { color: #6b7280; }

        .punkt { padding: 2mm 0 2.4mm 0; border-bottom: 0.6pt solid #e5e7eb; }
        .punkt-titel { font-weight: bold; color: #111827; margin-bottom: 1mm; page-break-after: avoid; }
        .punkt-nr { color: #64748b; font-weight: normal; margin-right: 2mm; }
        .punkt-text { margin-left: 9mm; }
        .vorbereitung { font-size: 8.5pt; color: #4b5563; background: #f8fafc; border-left: 1.5pt solid #cbd5e1; padding: 1mm 2mm; margin-bottom: 1.2mm; }
        .punkt-label { font-weight: bold; }

        .unterschriften { margin-top: 10mm; page-break-inside: avoid; }
        .unterschriften table { width: 100%; border-collapse: collapse; }
        .unterschriften td { width: 50%; padding: 0 6mm 0 0; vertical-align: bottom; }
        .unterschriften td + td { padding: 0 0 0 6mm; }
        .linie { border-top: 0.7pt solid #374151; padding-top: 1.2mm; font-size: 7.5pt; color: #4b5563; margin-top: 14mm; }
    </style>
</head>
<body>
<footer>
    Vertraulich – enthält personenbezogene Daten · {{ $kopf['name'] }} · Stand: {{ $stand->format('d.m.Y') }}
</footer>

<main>
    {{-- ── Briefkopf ── --}}
    <table class="kopf">
        <tr>
            <td style="width: 50%;">
                @if($kopf['logo'])
                    <img src="{{ $kopf['logo'] }}" alt="Logo">
                @endif
            </td>
            <td class="schule">
                <b>{{ $kopf['name'] }}</b>
                @foreach($kopf['adresse'] as $zeile)
                    {{ $zeile }}<br>
                @endforeach
                @if($kopf['kontakt'])
                    {{ $kopf['kontakt'] }}
                @endif
            </td>
        </tr>
    </table>
    <hr class="kopf-linie">

    <h1 class="titel">Protokoll Elterngespräch</h1>
    <p class="untertitel">
        {{ $schueler?->vorname }} {{ $schueler?->nachname }}@if($klasse) · Klasse {{ $klasse->name }}@endif · {{ $g->datum->format('d.m.Y') }}
    </p>

    @if($abgesagt)
        <div class="hinweis">Dieses Gespräch wurde abgesagt{{ $g->abgesagt_at ? ' (' . $g->abgesagt_at->format('d.m.Y') . ')' : '' }}.</div>
    @elseif($entwurf)
        <div class="hinweis">Entwurf – das Protokoll ist noch nicht abgeschlossen.</div>
    @endif

    {{-- ── Eckdaten ── --}}
    <table class="eckdaten">
        <tr><td class="label">Datum und Uhrzeit</td><td>{{ $g->datum->translatedFormat('l, d.m.Y') }}@if($zeit), {{ $zeit }}@endif</td></tr>
        <tr><td class="label">Ort / Form</td><td>{{ collect([$g->ort, $form])->filter()->implode(' · ') }}</td></tr>
        <tr><td class="label">Gesprächsleitung</td><td>{{ $g->user?->name ?? '—' }}</td></tr>
        <tr><td class="label">Für die Schule</td><td>{{ $schule ?: '—' }}</td></tr>
        <tr><td class="label">Sorgeberechtigte</td><td>{{ $g->eltern_teilnehmende ?: '—' }}</td></tr>
        @if($g->weitere_teilnehmende)
            <tr><td class="label">Weitere Personen</td><td>{{ $g->weitere_teilnehmende }}</td></tr>
        @endif
        <tr><td class="label">Schüler:in anwesend</td><td>{{ $g->schueler_anwesend ? 'ja' : 'nein' }}</td></tr>
    </table>

    @if($nr('anlass'))
        <h2><span class="nr">{{ $nr('anlass') }}.</span> Anlass</h2>
        <p class="text">{{ $g->anlass }}</p>
    @endif

    @if($nr('vorbereitung'))
        <h2><span class="nr">{{ $nr('vorbereitung') }}.</span> Vorbereitung</h2>
        <p class="text">{{ $g->vorbereitung_notizen }}</p>
    @endif

    @if($nr('punkte'))
        <h2><span class="nr">{{ $nr('punkte') }}.</span> Gesprächspunkte und Ergebnisse</h2>
        {{-- Als Blöcke statt Tabelle: dompdf kann eine Tabellenzeile nicht über Seiten teilen,
             lange Ergebnisse würden sonst in die Fußzeile laufen. --}}
        @foreach($punkte as $i => $punkt)
            <div class="punkt">
                <div class="punkt-titel"><span class="punkt-nr">{{ $nr('punkte') }}.{{ $i + 1 }}</span>{{ ($punkt['title'] ?? '') !== '' ? $punkt['title'] : 'Ohne Thema' }}</div>
                @if(($punkt['preparation'] ?? '') !== '')
                    <div class="punkt-text vorbereitung text"><span class="punkt-label">Vorbereitung:</span> {{ $punkt['preparation'] }}</div>
                @endif
                <div class="punkt-text text">{{ ($punkt['result'] ?? '') !== '' ? $punkt['result'] : '—' }}</div>
            </div>
        @endforeach
    @endif

    @if($nr('verlauf'))
        <h2><span class="nr">{{ $nr('verlauf') }}.</span> Gesprächsverlauf / Sonstiges</h2>
        <p class="text">{{ $g->verlauf }}</p>
    @endif

    @if($nr('vereinbarungen'))
        <h2><span class="nr">{{ $nr('vereinbarungen') }}.</span> Vereinbarungen</h2>
        <table class="liste">
            <thead><tr><th class="nr-spalte">Nr.</th><th>Vereinbarung</th><th class="schmal">Verantwortlich</th><th class="schmal">bis</th></tr></thead>
            <tbody>
                @foreach($g->vereinbarungen as $i => $v)
                    <tr>
                        <td class="nr-spalte">{{ $i + 1 }}</td>
                        <td class="text">{{ $v->text }}</td>
                        <td class="schmal">{{ $verantwortlich[$v->verantwortlich] ?? '—' }}</td>
                        <td class="schmal">{{ $v->bis?->format('d.m.Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($nr('naechster_termin'))
        <h2><span class="nr">{{ $nr('naechster_termin') }}.</span> Nächster Termin</h2>
        <p>{{ $g->naechster_termin->translatedFormat('l, d.m.Y') }}</p>
    @endif

    {{-- ── Unterschriften ── --}}
    {{-- Überschrift und Linien bleiben zusammen; symmetrisch: links Schule, rechts Sorgeberechtigte --}}
    <div class="unterschriften">
        <h2>Unterschriften</h2>
        <table>
            <tr>
                <td><div class="linie">Ort, Datum</div></td>
                <td><div class="linie">Ort, Datum</div></td>
            </tr>
            <tr>
                <td><div class="linie">Für die Schule{{ $g->user ? ': ' . $g->user->name : '' }}</div></td>
                <td><div class="linie">Sorgeberechtigte:r</div></td>
            </tr>
            <tr>
                <td>@if($g->schueler_anwesend)<div class="linie">Schüler:in{{ $schueler ? ': ' . $schueler->vorname . ' ' . $schueler->nachname : '' }}</div>@endif</td>
                <td><div class="linie">Sorgeberechtigte:r</div></td>
            </tr>
        </table>
    </div>
</main>
</body>
</html>
