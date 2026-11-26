<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $seite->titel }}</title>
    <style>
        @page { margin: 18mm 18mm 22mm 20mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #1f2937; line-height: 1.5; }
        .kopf { width: 100%; border-collapse: collapse; margin-bottom: 5mm; }
        .kopf td { vertical-align: top; padding: 0; }
        .kopf img { height: 15mm; }
        .kopf .schule { text-align: right; font-size: 8pt; color: #4b5563; line-height: 1.45; }
        .kopf .schule b { display: block; font-size: 10.5pt; color: #111827; }
        .kopf-linie { border: 0; border-top: 1.2pt solid #1E40AF; margin: 0 0 6mm 0; }
        .pfad { font-size: 8pt; color: #6b7280; margin-bottom: 1mm; }
        .titel { font-size: 18pt; font-weight: bold; color: #111827; margin: 0 0 1mm 0; }
        .stand { font-size: 8pt; color: #6b7280; margin-bottom: 6mm; }
        h1 { font-size: 15pt; } h2 { font-size: 13pt; color: #1E40AF; border-bottom: 0.6pt solid #c7d2fe; padding-bottom: 1mm; margin: 6mm 0 2mm; }
        h3 { font-size: 11pt; margin: 4mm 0 1.5mm; } h4 { font-size: 10pt; margin: 3mm 0 1mm; }
        p { margin: 0 0 2.5mm 0; }
        ul, ol { margin: 0 0 2.5mm 0; padding-left: 6mm; }
        a { color: #1d4ed8; text-decoration: none; }
        .wiki-anker { display: none; }
        table { border-collapse: collapse; margin: 0 0 3mm 0; }
        th, td { border: 0.5pt solid #d1d5db; padding: 1.2mm 2mm; vertical-align: top; text-align: left; }
        th { background: #f1f5f9; }
        img { max-width: 170mm; height: auto; }
        .wiki-bild-klein { max-width: 60mm; } .wiki-bild-mittel { max-width: 110mm; }
        figure { margin: 0 0 3mm 0; } figcaption { font-size: 8pt; color: #6b7280; }
        pre { background: #f1f5f9; padding: 2mm 3mm; font-size: 8pt; white-space: pre-wrap; }
        blockquote { border-left: 2pt solid #e5e7eb; margin: 0 0 3mm 0; padding-left: 3mm; color: #4b5563; }
        .wiki-hinweis { border: 0.6pt solid #bfdbfe; background: #eff6ff; padding: 2mm 3mm; margin: 0 0 3mm 0; }
        .wiki-hinweis-tipp { border-color: #a7f3d0; background: #ecfdf5; }
        .wiki-hinweis-warnung { border-color: #fde68a; background: #fffbeb; }
        .wiki-link-rot { color: #dc2626; }
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

    @if($pfad->isNotEmpty())
        <div class="pfad">Wiki › {{ $pfad->pluck('titel')->implode(' › ') }}</div>
    @endif
    <h1 class="titel">{{ $seite->titel }}</h1>
    <div class="stand">Stand {{ $seite->bearbeitet_at?->format('d.m.Y') }} · zuletzt geändert von {{ $seite->geaendert->name }} · gedruckt {{ $stand->format('d.m.Y H:i') }}</div>

    <div class="inhalt">{!! $inhalt !!}</div>
</body>
</html>
