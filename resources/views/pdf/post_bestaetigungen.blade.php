@php
    $statusLabel = \App\Exports\PostBestaetigungenExport::STATUS;
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Lesebestätigungen – {{ $post->header }}</title>
    <style>
        @page { margin: 18mm 18mm 24mm 20mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #1f2937; line-height: 1.4; }
        .kopf { width: 100%; border-collapse: collapse; margin-bottom: 7mm; }
        .kopf td { vertical-align: top; padding: 0; }
        .kopf img { height: 18mm; }
        .kopf .schule { text-align: right; font-size: 8pt; color: #4b5563; line-height: 1.45; }
        .kopf .schule b { display: block; font-size: 11pt; color: #111827; margin-bottom: 0.8mm; }
        .kopf-linie { border: 0; border-top: 1.4pt solid #1E40AF; margin: 0 0 8mm 0; }
        .titel { font-size: 16pt; font-weight: bold; color: #111827; margin: 0; }
        .untertitel { font-size: 10pt; color: #4b5563; margin: 1mm 0 6mm 0; }
        .eckdaten { width: 100%; border-collapse: collapse; background: #f8fafc; border: 0.6pt solid #e2e8f0; margin-bottom: 7mm; }
        .eckdaten td { padding: 1.6mm 3mm; vertical-align: top; border-bottom: 0.6pt solid #eef2f6; }
        .eckdaten .label { width: 42mm; color: #64748b; font-size: 8.5pt; }
        table.liste { width: 100%; border-collapse: collapse; }
        table.liste th { text-align: left; font-size: 8pt; color: #475569; background: #f1f5f9; padding: 1.6mm 2mm; border-bottom: 0.8pt solid #cbd5e1; }
        table.liste td { padding: 1.8mm 2mm; vertical-align: top; border-bottom: 0.6pt solid #e5e7eb; }
        table.liste tr:nth-child(even) td { background: #fafbfc; }
        .muted { color: #6b7280; font-size: 8pt; }
        .ueberfaellig { color: #b91c1c; font-weight: bold; }
        .bestaetigt { color: #047857; }
    </style>
</head>
<body>
    <table class="kopf">
        <tr>
            <td>@if($kopf['logo'])<img src="{{ $kopf['logo'] }}" alt="">@endif</td>
            <td class="schule">
                <b>{{ $kopf['name'] }}</b>
                @foreach($kopf['adresse'] as $zeile){{ $zeile }}<br>@endforeach
                @if($kopf['kontakt']){{ $kopf['kontakt'] }}@endif
            </td>
        </tr>
    </table>
    <hr class="kopf-linie">

    <p class="titel">Nachweis Lesebestätigungen</p>
    <p class="untertitel">{{ $post->header }}</p>

    <table class="eckdaten">
        <tr><td class="label">Gruppen</td><td>{{ $post->groups->pluck('name')->join(', ') }}</td></tr>
        <tr><td class="label">Verfasst von</td><td>{{ $post->author->name }}</td></tr>
        <tr><td class="label">Veröffentlicht</td><td>{{ $post->datum()?->format('d.m.Y H:i') }}</td></tr>
        <tr><td class="label">Bestätigung angefordert</td><td>{{ ($post->bestaetigung_angefordert_at ?? $post->datum())?->format('d.m.Y H:i') }}{{ $post->bestaetigung_frist ? ', Frist '.$post->bestaetigung_frist->format('d.m.Y') : '' }}</td></tr>
        <tr><td class="label">Stand</td><td>{{ $statistik['bestaetigt'] }} von {{ $statistik['gesamt'] }} bestätigt ({{ $statistik['offen'] }} offen, {{ $statistik['ueberfaellig'] }} überfällig) · {{ $stand->format('d.m.Y H:i') }} Uhr</td></tr>
    </table>

    <table class="liste">
        <thead>
            <tr><th>Name</th><th>Status</th><th>bestätigt am</th><th>Frist</th></tr>
        </thead>
        <tbody>
            @foreach($liste as $zeile)
                <tr>
                    <td>
                        {{ $zeile['user']->name }}
                        @if($zeile['ausgeschieden'])<br><span class="muted">nicht mehr in der Gruppe</span>@endif
                    </td>
                    <td class="{{ $zeile['status'] }}">{{ $statusLabel[$zeile['status']] }}</td>
                    <td>{{ $zeile['bestaetigt_at']?->format('d.m.Y H:i') ?? '–' }}</td>
                    <td>{{ $zeile['frist']?->format('d.m.Y') ?? '–' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
