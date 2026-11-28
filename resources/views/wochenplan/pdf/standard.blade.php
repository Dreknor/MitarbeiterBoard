{{--
    Wochenplan – Standard-Layout nach der Papiervorlage der Schule:
    Kopf (Titel + Klasse, Namenszeile), ein umrahmter Block mit „Tägliche Übung“ (Übungsliste +
    Wochenraster Woche × Wochentag × Übung), darunter Fächer mit nummerierten Aufgaben und je
    Aufgabe eigenen Abhak-Feldern (fertig / kontrolliert / Unterschrift), Selbsteinschätzung und
    – auf eigener Seite – „Empfehlungen für die Freiarbeit“ je Fach.
    Freiwillige Aufgaben stehen mit * statt Nummer. Bilder/Symbole kommen als Data-URI
    (DomPDF rendert weder Farb-Emojis noch Inline-SVG in Tabellen).
--}}
@php
    // ── Typografie ──────────────────────────────────────────────────────────
    $customPt = $config['typografie']['schriftgroesse_pt'] ?? null;
    $basisPt = $customPt ?: match($formatvorlage->schriftgroesse ?? 'normal') {
        'gross'      => 14,
        'sehr_gross' => 18,
        default      => 11,
    };
    $pt = fn (float $delta = 0, float $min = 6) => max($min, $basisPt + $delta) . 'pt';
    $schrift = $schriftCss ?? ($formatvorlage->schriftart ?: 'Helvetica, Arial, sans-serif');
    // roh ausgeben (Anführungszeichen dürfen nicht zu &#039; werden) – daher nur unbedenkliche Zeichen zulassen
    $schrift = preg_replace('/[^A-Za-z0-9 ,\'"\-]/', '', $schrift) ?: 'Helvetica, sans-serif';
    $zeilenabstand = $config['typografie']['zeilenabstand'] ?? 1.3;
    $raender = array_merge(['oben' => 10, 'rechts' => 15, 'unten' => 12, 'links' => 15], $config['seitenraender'] ?? []);
    $abstandAufgaben = (float) ($config['abstände']['zwischen_aufgaben'] ?? 3);
    $minZeile = (float) ($config['abstände']['min_fach_zeilenhoehe'] ?? 0);

    // ── Spalten ─────────────────────────────────────────────────────────────
    $sp = $config['spalten'] ?? [];
    $zeigeCheck        = $sp['zeige_check_spalte'] ?? true;
    $zeigeKontrolliert = $sp['zeige_kontrolliert_spalte'] ?? true;
    $zeigeUnterschrift = $sp['zeige_unterschrift_spalte'] ?? true;
    $zeigeDauer        = $sp['zeige_dauer'] ?? false;
    $breiteFach   = $sp['fach'] ?? '22%';
    $breiteCheck  = $sp['check'] ?? '7%';
    $breiteKontr  = $sp['kontrolliert'] ?? '9%';
    $breiteUnter  = $sp['unterschrift'] ?? '9%';
    $abhakSpalten = (int) $zeigeCheck + (int) $zeigeKontrolliert + (int) $zeigeUnterschrift + (int) $zeigeDauer;
    $spaltenGesamt = 2 + $abhakSpalten;
    // DomPDF richtet feste Tabellen nach der ersten Zeile aus → unsichtbare Maßzeile mit diesen Breiten
    $prozent = fn ($w, $std) => max(1.0, min(60.0, (float) (is_string($w) ? rtrim($w, '% ') : $w) ?: $std));
    $breiten = ['fach' => $prozent($breiteFach, 22)];
    if ($zeigeDauer) { $breiten['dauer'] = 9.0; }
    if ($zeigeCheck) { $breiten['check'] = $prozent($breiteCheck, 7); }
    if ($zeigeKontrolliert) { $breiten['kontrolliert'] = $prozent($breiteKontr, 9); }
    if ($zeigeUnterschrift) { $breiten['unterschrift'] = $prozent($breiteUnter, 9); }
    $breiten = array_merge(array_slice($breiten, 0, 1), ['aufgaben' => max(25.0, 100 - array_sum($breiten))], array_slice($breiten, 1));
    $leer = '……';

    // ── Kopf ────────────────────────────────────────────────────────────────
    $kopf = $config['header'] ?? [];
    $klassenName = $plan->klasse?->name ?? $plan->schueler?->klasse?->name;

    // ── Kleine Grafiken (Data-URI) ──────────────────────────────────────────
    $svg = fn (string $inhalt, int $w = 24, int $h = 24) => 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 24 24">'.$inhalt.'</svg>');
    $haken = $svg('<polyline points="4,12 10,18 20,6" fill="none" stroke="#000" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>');
    $gesicht = fn (string $mund) => $svg('<circle cx="12" cy="12" r="10" fill="none" stroke="#000" stroke-width="1.4"/><circle cx="8.5" cy="9.5" r="1.2"/><circle cx="15.5" cy="9.5" r="1.2"/>'.$mund);
    $smileyGut  = $gesicht('<path d="M7 14.5 Q12 19 17 14.5" fill="none" stroke="#000" stroke-width="1.4" stroke-linecap="round"/>');
    $smileyOkay = $gesicht('<line x1="7.5" y1="15.5" x2="16.5" y2="15.5" stroke="#000" stroke-width="1.4" stroke-linecap="round"/>');
    $smileySchw = $gesicht('<path d="M7 17.5 Q12 13 17 17.5" fill="none" stroke="#000" stroke-width="1.4" stroke-linecap="round"/>');

    // ── Tägliche Übungen: Wochenraster ──────────────────────────────────────
    $uebungen = $plan->taegliche_uebungen_aktiv ? $plan->taeglicheUebungen->values() : collect();
    $tuCfg = $config['taegliche_uebungen'] ?? [];
    $tuLayout = $tuCfg['layout'] ?? 'wochenweise';
    $tage = [];
    if ($plan->gueltig_von && $plan->gueltig_bis && $uebungen->isNotEmpty()) {
        for ($d = $plan->gueltig_von->copy(); $d->lte($plan->gueltig_bis); $d->addDay()) {
            if ($d->isWeekday()) {
                $tage[] = $d->copy();
            }
        }
    }
    $wochen = [];
    foreach ($tage as $tag) {
        $wochen[$tag->format('o-W')][$tag->dayOfWeekIso] = $tag;
    }
    ksort($wochen);
    $wochen = array_values($wochen);
    $wochentage = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag'];
    // Wie in der Papiervorlage: eine Spalte je Kürzel (✍ = Deutsch/Schreiben, 5+5 = Mathe). Übungen ohne
    // Kürzel bekommen es aus dem Text; Übungen mit gleichem Kürzel („Woche 1: …“, „Woche 2: …“) teilen sich die Spalte.
    $uebungen = \App\Support\WpUebungsSpalten::gruppieren($uebungen);
    $kuerzel = fn ($u, $i = 0, bool $kopf = false) => \App\Support\WpUebungsSpalten::html($u->kuerzel, $kopf);

    // ── Aufgaben: Arbeitsaufträge und Freiarbeit trennen ────────────────────
    $faecher = $plan->planFaecher->map(function ($pf) {
        $alle = $pf->aufgaben;
        return (object) [
            'fach' => $pf,
            'auftraege' => $alle->where('freiarbeit', false)->values(),
            'freiarbeit' => $alle->where('freiarbeit', true)->values(),
            'nurFreiarbeit' => $alle->isNotEmpty() && $alle->every(fn ($a) => $a->freiarbeit),
        ];
    });
    $hatFreiwillige = $faecher->contains(fn ($f) => $f->auftraege->contains('freiwillig', true));
    $freiarbeit = $faecher->filter(fn ($f) => $f->freiarbeit->isNotEmpty());
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $plan->name }}</title>
    <style>
        @page { margin: {{ $raender['oben'] }}mm {{ $raender['rechts'] }}mm {{ $raender['unten'] }}mm {{ $raender['links'] }}mm; }
        @font-face { font-family: 'OpenDyslexic'; src: url("{{ storage_path('fonts/OpenDyslexic-Regular.otf') }}") format('opentype'); }
        @if(!empty($comicSans))
            @font-face { font-family: 'WpComicSans'; font-style: normal; font-weight: normal; src: url("{{ $comicSans['normal'] }}") format('truetype'); }
            @font-face { font-family: 'WpComicSans'; font-style: normal; font-weight: bold; src: url("{{ $comicSans['bold'] ?? $comicSans['normal'] }}") format('truetype'); }
        @endif
        /* kein margin auf html – sonst ignoriert DomPDF den @page-Rand */
        body, body * { margin: 0; padding: 0; }
        body { font-family: {!! $schrift !!}; font-size: {{ $pt() }}; line-height: {{ $zeilenabstand }}; color: #000; }

        /* Kopf */
        .kopf { text-align: center; font-size: {{ $pt(5) }}; line-height: 1.2; margin: 0 0 3mm 0; }
        .kopf-abstand { padding-left: 14mm; }
        .name { font-size: {{ $pt(4) }}; line-height: 1.2; margin: 0 0 0.5mm 6mm; }
        .name-wert { font-size: {{ $pt(0) }}; }
        .name-linie { display: inline-block; width: 70mm; border-bottom: 1px solid #000; }
        .name u { text-decoration: underline; }
        .freitext { font-size: {{ $pt(-1.5) }}; color: #444; margin-bottom: 2mm; }

        /* Rahmen-Tabelle */
        /* getrenntes Rahmenmodell: jede Zelle zeichnet links (+ oben/unten nach Bedarf), die letzte Spalte rechts –
           DomPDF verliert sonst bei rowspan im collapse-Modell Ränder */
        table.plan { width: 100%; border-collapse: separate; border-spacing: 0; table-layout: fixed; }
        table.plan td { vertical-align: top; border-left: 1px solid #000; }
        table.plan td.letzte { border-right: 1px solid #000; }
        table.plan tr.mass td { border: 0; height: 0; padding: 0; font-size: 1pt; line-height: 0; }
        .rand-oben { border-top: 1px solid #000; }
        .rand-unten { border-bottom: 1px solid #000; }

        /* Tägliche Übung */
        .tu { padding: 1.5mm 3mm 3mm 3mm; }
        .tu-titel { text-align: center; text-decoration: underline; font-size: {{ $pt(1) }}; margin-bottom: 2mm; }
        .tu-liste { width: 100%; border-collapse: collapse; margin-bottom: 2.5mm; }
        table.plan .tu-liste td, table.plan .aufgabe-zeile td { border: 0; }
        table.plan td.innen { padding: 0; }
        tr.fach-zeile { page-break-inside: avoid; }
        table.aufgaben-innen { width: 100%; border-collapse: separate; border-spacing: 0; table-layout: fixed; }
        table.aufgaben-innen td { border-left: 0; vertical-align: top; }
        table.aufgaben-innen td.abhak { border-left: 1px solid #000; }
        .tu-liste td { padding: 0.3mm 0; vertical-align: top; }
        .tu-liste .kz { width: 12mm; white-space: nowrap; }
        table.raster { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.raster .label, table.raster thead .ecke { width: 13%; }
        table.raster th, table.raster td { border: 1px solid #000; text-align: center; font-size: {{ $pt(-1.5) }}; line-height: 1.1; padding: 0.4mm 1mm; height: 4.6mm; }
        table.raster th { font-weight: bold; }
        table.raster .label { border-left: 0; text-align: center; font-weight: normal; font-size: {{ $pt(0) }}; white-space: nowrap; }
        table.raster thead .ecke { border: 0; }
        table.raster .schattiert { background: #eeece1; }
        table.raster .fehlt { background: #d9d9d9; }
        table.liste-tage { width: 100%; border-collapse: collapse; }
        table.liste-tage th, table.liste-tage td { border: 1px solid #000; text-align: center; font-size: {{ $pt(-1.5) }}; padding: 0.4mm 1mm; height: 4.6mm; }

        /* Kopfzeile Aufgaben */
        .kopf-aufgaben { text-align: center; font-size: {{ $pt(2) }}; padding: 1mm; }
        .kopf-klein { text-align: center; font-size: {{ $pt(-2.5) }}; line-height: 1.1; padding: 0.8mm 0.5mm; }

        /* Fächer */
        .fach { text-align: center; vertical-align: middle !important; padding: 2mm 1.5mm; }
        .fach-name { font-size: {{ $pt(4) }}; line-height: 1.2; }
        .fach-symbol { margin-top: 1mm; }
        .aufgabe { padding: {{ $abstandAufgaben / 2 + 0.8 }}mm 2mm {{ $abstandAufgaben / 2 + 0.8 }}mm 2mm; @if($minZeile > 0) height: {{ $minZeile }}mm; @endif }
        .aufgabe-zeile { width: 100%; border-collapse: collapse; }
        .aufgabe-zeile td { vertical-align: top; padding: 0; }
        .aufgabe-zeile .nr { width: 6mm; white-space: nowrap; }
        .dauer { font-size: {{ $pt(-1.5) }}; color: #444; }
        .abhak { text-align: center; vertical-align: bottom !important; padding: 1mm 0.5mm {{ $abstandAufgaben / 2 + 0.8 }}mm 0.5mm; font-size: {{ $pt(-1.5) }}; }

        /* Selbsteinschätzung */
        .selbst-titel { margin-top: 2.5mm; }
        table.selbst { width: 100%; border-collapse: collapse; }
        table.selbst td { border: 1px solid #000; text-align: center; padding: 0.8mm; }
        .fussnote { font-size: {{ $pt(-2) }}; margin-top: 1mm; }
        .fusstext { font-size: {{ $pt(-1.5) }}; color: #444; margin-top: 2mm; }

        /* Freiarbeit */
        .neue-seite { page-break-before: always; }
        .fa-titel { font-weight: bold; text-decoration: underline; font-size: {{ $pt(1.5) }}; margin-bottom: 4mm; }
        .fa-fach { font-size: {{ $pt(1.5) }}; margin: 4mm 0 2mm 0; }
        .fa-fach u { text-decoration: underline; }
        table.fa { width: 88%; margin-left: 12%; border-collapse: collapse; }
        table.fa td { border: 1px solid #000; padding: 0.6mm 1.5mm; vertical-align: top; }
        table.fa .kasten { width: 9%; }
    </style>
</head>
<body>

{{-- ══ Kopf ═══════════════════════════════════════════════════════════════ --}}
{{-- wie in der Vorlage: Titel und Klasse zentriert in einer unterstrichenen Zeile --}}
<div class="kopf">
    <u>{{ $plan->isSchuelerplan() ? \Illuminate\Support\Str::before($plan->name, ' – ') : $plan->name }}@if(($kopf['zeige_zeitraum'] ?? true) && $plan->zeitraum) vom {{ $plan->zeitraum }}@endif</u>@if($klassenName && ($kopf['zeige_klasse'] ?? true))<span class="kopf-abstand">&nbsp;</span><u>{{ $klassenName }}</u>@endif
</div>
@if($plan->isSchuelerplan() && $plan->schueler)
    <div class="name">Name: <span class="name-wert">{{ $plan->schueler->vorname }} {{ $plan->schueler->nachname }}</span></div>
@elseif($kopf['zeige_name_feld'] ?? true)
    <div class="name">Name: <span class="name-linie">&nbsp;</span></div>
@endif
@if(!empty($kopf['freitext']))
    <div class="freitext">{{ $kopf['freitext'] }}</div>
@endif

{{-- ══ Rahmen: Tägliche Übung + Fächer ═════════════════════════════════════ --}}
<table class="plan">
    <tbody>
        <tr class="mass">
            @foreach($breiten as $b)<td style="width: {{ round($b, 2) }}%;"></td>@endforeach
        </tr>
    @if($uebungen->isNotEmpty())
        <tr>
            <td colspan="{{ $spaltenGesamt }}" class="tu rand-oben letzte">
                <div class="tu-titel">Tägliche Übung</div>
                <table class="tu-liste">
                    @foreach($uebungen as $i => $uebung)
                        <tr>
                            <td class="kz">{!! $kuerzel($uebung, $i) !!}</td>
                            <td>{!! nl2br(e($uebung->aufgabe)) !!}</td>
                        </tr>
                    @endforeach
                </table>

                @if($tage)
                    @if($tuLayout === 'wochenweise' || $tuLayout === 'raster')
                        {{-- Wochen als Zeilen, Wochentage mit je einer Unterspalte pro Übung (wie Papiervorlage) --}}
                        @php $n = $uebungen->count(); @endphp
                        <table class="raster">
                            <thead>
                                <tr>
                                    <th class="ecke"></th>
                                    @foreach($wochentage as $name)
                                        <th colspan="{{ $n }}">{{ $name }}</th>
                                    @endforeach
                                </tr>
                                <tr>
                                    <th class="ecke"></th>
                                    @foreach($wochentage as $dow => $name)
                                        @foreach($uebungen as $i => $uebung)
                                            <th class="{{ $i % 2 === 1 ? 'schattiert' : '' }}" style="font-weight:normal;">{!! $kuerzel($uebung, $i, true) !!}</th>
                                        @endforeach
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($wochen as $w => $wochenTage)
                                    <tr>
                                        <td class="label">Woche {{ $w + 1 }}</td>
                                        @foreach($wochentage as $dow => $name)
                                            @foreach($uebungen as $i => $uebung)
                                                <td class="{{ isset($wochenTage[$dow]) ? ($i % 2 === 1 ? 'schattiert' : '') : 'fehlt' }}">&nbsp;</td>
                                            @endforeach
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @elseif($tuLayout === 'vertikal')
                        {{-- Tage als Zeilen, Übungen als Spalten --}}
                        <table class="liste-tage">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">Tag</th>
                                    @foreach($uebungen as $i => $uebung)<th>{!! $kuerzel($uebung, $i) !!}</th>@endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tage as $tag)
                                    <tr>
                                        <td style="text-align:left;">{{ mb_substr($wochentage[$tag->dayOfWeekIso], 0, 2) }} {{ $tag->format('d.m.') }}</td>
                                        @foreach($uebungen as $uebung)<td>&nbsp;</td>@endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        {{-- Horizontal: Übungen als Zeilen, je Woche eine Tabelle mit den Tagen als Spalten --}}
                        @foreach($wochen as $w => $wochenTage)
                            <table class="liste-tage" style="{{ $w > 0 ? 'margin-top:1.5mm;' : '' }}">
                                <thead>
                                    <tr>
                                        <th style="width: 25%;">Woche {{ $w + 1 }}</th>
                                        @foreach($wochentage as $dow => $name)
                                            <th>{{ mb_substr($name, 0, 2) }}@isset($wochenTage[$dow]) {{ $wochenTage[$dow]->format('d.m.') }}@endisset</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($uebungen as $i => $uebung)
                                        <tr>
                                            <td style="text-align:left;">{!! $kuerzel($uebung, $i) !!}</td>
                                            @foreach($wochentage as $dow => $name)
                                                <td class="{{ isset($wochenTage[$dow]) ? '' : 'fehlt' }}" style="{{ isset($wochenTage[$dow]) ? '' : 'background:#d9d9d9;' }}">&nbsp;</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endforeach
                    @endif
                @endif
            </td>
        </tr>
    @endif

        {{-- Kopfzeile der Aufgaben --}}
        @php
            $kopfSpalten = array_filter([
                'dauer' => $zeigeDauer ? 'Dauer' : null,
                'check' => $zeigeCheck ? 'fertig<br><img src="'.$haken.'" width="8" height="8" alt="">' : null,
                'kontrolliert' => $zeigeKontrolliert ? 'kontrol-<br>liert' : null,
                'unterschrift' => $zeigeUnterschrift ? 'Unter-<br>schrift' : null,
            ]);
            $letzteSpalte = array_key_last($kopfSpalten) ?? 'aufgaben';
            // Breiten der inneren Tabelle relativ zum Bereich rechts der Fach-Spalte
            $rest = max(1, 100 - $breiten['fach']);
            $innenBreiten = array_map(fn ($b) => round($b / $rest * 100, 3), array_diff_key($breiten, ['fach' => 1]));
        @endphp
        <tr>
            <td class="rand-oben"></td>
            <td class="rand-oben kopf-aufgaben {{ $letzteSpalte === 'aufgaben' ? 'letzte' : '' }}">Aufgaben</td>
            @foreach($kopfSpalten as $key => $label)
                <td class="rand-oben kopf-klein {{ $key === $letzteSpalte ? 'letzte' : '' }}">{!! $label !!}</td>
            @endforeach
        </tr>

        @foreach($faecher->reject(fn ($f) => $f->nurFreiarbeit) as $eintrag)
            @php
                $pf = $eintrag->fach;
                $auftraege = $eintrag->auftraege;
                $zeilen = max(1, $auftraege->count());
                $nummerieren = $auftraege->count() > 1;
                $nr = 0;
                $letztes = $loop->last;
            @endphp
            {{-- Eine Zeile je Fach mit innerer Tabelle: DomPDF zerlegt rowspan-Zellen beim Seitenumbruch falsch --}}
            <tr class="fach-zeile">
                <td class="fach rand-oben {{ $letztes ? 'rand-unten' : '' }}">
                    @if($pf->display_name !== '')<div class="fach-name" @if(mb_strlen($pf->display_name) > 11 && ! str_contains($pf->display_name, ' ')) style="font-size: {{ $pt(1.5) }};" @endif>{!! \App\Models\Wochenplan\WpFach::zeichenAlsPdfHtml($pf->display_name, null, 18, false) !!}</div>@endif
                    @if($pf->pdf_symbol_html)<div class="fach-symbol">{!! $pf->pdf_symbol_html !!}</div>@endif
                </td>
                <td colspan="{{ $spaltenGesamt - 1 }}" class="innen letzte rand-oben {{ $letztes ? 'rand-unten' : '' }}">
                    <table class="aufgaben-innen">
                        <tr class="mass">
                            <td style="width: {{ $innenBreiten['aufgaben'] }}%;"></td>
                            @foreach($kopfSpalten as $key => $label)<td style="width: {{ $innenBreiten[$key] }}%;"></td>@endforeach
                        </tr>
                        @for($z = 0; $z < $zeilen; $z++)
                            @php
                                $aufgabe = $auftraege[$z] ?? null;
                                if ($aufgabe && ! $aufgabe->freiwillig) { $nr++; }
                            @endphp
                            <tr>
                                {{-- einzelne Aufgabe: Mindesthöhe, damit die Spaltenlinien bis unter Fachname + Symbol reichen --}}
                                <td class="aufgabe" @if($zeilen === 1) style="height: {{ $pf->pdf_symbol_html && $pf->display_name !== '' ? 16 : 9 }}mm;" @endif>
                                    @if($aufgabe)
                                        <table class="aufgabe-zeile">
                                            <tr>
                                                @if($aufgabe->freiwillig)
                                                    <td class="nr">*</td>
                                                @elseif($nummerieren)
                                                    <td class="nr">{{ $nr }}.</td>
                                                @endif
                                                <td>
                                                    {!! nl2br(e($aufgabe->aufgabe)) !!}
                                                    @if($aufgabe->dauer && ! $zeigeDauer)<span class="dauer"> ({{ $aufgabe->dauer }})</span>@endif
                                                    @include('wochenplan.pdf._link', ['aufgabe' => $aufgabe])
                                                </td>
                                            </tr>
                                        </table>
                                    @else
                                        &nbsp;
                                    @endif
                                </td>
                                @foreach($kopfSpalten as $key => $label)
                                    <td class="abhak">{{ $key === 'dauer' ? $aufgabe?->dauer : ($aufgabe && $key !== 'unterschrift' ? $leer : '') }}</td>
                                @endforeach
                            </tr>
                        @endfor
                    </table>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

@if($hatFreiwillige)
    <div class="fussnote">* freiwillige Aufgabe</div>
@endif

{{-- ══ Selbsteinschätzung ══════════════════════════════════════════════════ --}}
@if($plan->selbsteinschaetzung > 0 && ($config['footer']['zeige_selbsteinschaetzung'] ?? true))
    <div class="selbst-titel">So habe ich gearbeitet:</div>
    <table class="selbst">
        <tr>
            @if((int) $plan->selbsteinschaetzung === 2)
                @for($i = 1; $i <= 10; $i++)<td style="width:10%;">{{ $i }}</td>@endfor
            @else
                <td style="width:33.3%;"><img src="{{ $smileyGut }}" width="18" height="18" alt="gut"></td>
                <td style="width:33.3%;"><img src="{{ $smileyOkay }}" width="18" height="18" alt="okay"></td>
                <td style="width:33.3%;"><img src="{{ $smileySchw }}" width="18" height="18" alt="schwierig"></td>
            @endif
        </tr>
    </table>
@endif

@if(!empty($config['footer']['freitext']))
    <div class="fusstext">{{ $config['footer']['freitext'] }}</div>
@endif

{{-- ══ Empfehlungen für die Freiarbeit (eigene Seite) ══════════════════════ --}}
@if($freiarbeit->isNotEmpty())
    <div class="neue-seite">
        <div class="fa-titel">Empfehlungen für die Freiarbeit</div>
        @foreach($freiarbeit as $eintrag)
            <div class="fa-fach"><u>{!! \App\Models\Wochenplan\WpFach::zeichenAlsPdfHtml($eintrag->fach->display_name ?: 'Weitere', null, 14, false) !!}</u>:</div>
            <table class="fa">
                @foreach($eintrag->freiarbeit as $aufgabe)
                    <tr>
                        <td>
                            @if($aufgabe->freiwillig)* @endif{!! nl2br(e($aufgabe->aufgabe)) !!}
                            @include('wochenplan.pdf._link', ['aufgabe' => $aufgabe])
                        </td>
                        <td class="kasten">&nbsp;</td>
                        <td class="kasten">&nbsp;</td>
                    </tr>
                @endforeach
            </table>
        @endforeach
    </div>
@endif

</body>
</html>
