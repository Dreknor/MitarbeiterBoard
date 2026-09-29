@php
    $hm = function ($sekunden, bool $vorzeichen = false) {
        $minuten = (int) round($sekunden / 60);
        $zeichen = $minuten < 0 ? '-' : ($vorzeichen && $minuten > 0 ? '+' : '');
        $minuten = abs($minuten);
        return $zeichen.intdiv($minuten, 60).':'.str_pad((string) ($minuten % 60), 2, '0', STR_PAD_LEFT);
    };
    $fmt = fn ($wert) => \App\Services\Personal\Zeit\UrlaubskontoService::format((float) $wert);
    $saldoVorher = (int) ($timesheet_old?->working_time_account ?? 0);
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Arbeitszeitnachweis {{ $month->format('m/Y') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 11px; color: #111; margin: 8mm 10mm; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .kopf { display: table; width: 100%; margin-bottom: 8px; }
        .kopf > div { display: table-cell; vertical-align: top; }
        .kopf .logo { text-align: right; }
        .kopf img { max-height: 48px; }
        .meta { color: #555; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #999; padding: 3px 5px; }
        th { background: #e5e7eb; text-align: left; font-size: 10px; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tr.frei td { background: #dbe7f7; }
        .minus { color: #b91c1c; }
        .plus { color: #047857; }
        .unten { display: table; width: 100%; margin-top: 12px; }
        .unten > div { display: table-cell; vertical-align: top; }
        .summen td, .summen th { border: 1px solid #999; }
        .unterschrift { padding-left: 30px; }
        .linie { border-bottom: 1px solid #111; height: 34px; margin-bottom: 4px; }
    </style>
</head>
<body>
<div class="kopf">
    <div>
        <h1>Arbeitszeitnachweis {{ $month->locale('de')->isoFormat('MMMM YYYY') }}</h1>
        <div>{{ $employe->vorname }} {{ $employe->familienname ?? $employe->name }}</div>
        <div class="meta">Status: {{ $timesheet->status_label }}@if($timesheet->is_locked) ({{ $timesheet->locked_at->format('d.m.Y') }})@endif · erstellt {{ now()->format('d.m.Y H:i') }}</div>
    </div>
    <div class="logo">
        @if(config('app.logo'))<img src="{{ asset('img/'.config('app.logo')) }}" alt="">@endif
    </div>
</div>

<table>
    <thead>
    <tr>
        <th>Tag</th>
        <th>Arbeitszeiten</th>
        <th class="num">Pause</th>
        <th class="num">Ist / Soll</th>
        <th>Bemerkungen</th>
        <th class="num">Saldo</th>
    </tr>
    </thead>
    <tbody>
    @foreach($zeilen as $zeile)
        @php
            $zeiten = $zeile['entries']->reject->is_credit;
            $bemerkungen = $zeile['entries']->pluck('comment')->filter()->unique()->reject(fn ($c) => in_array($c, ['digitale Zeiterfassung', 'aus Dienstplan übernommen', 'aus Dienstplan erstellt'], true));
            if ($zeile['feiertag']) { $bemerkungen->push($zeile['feiertag']); }
        @endphp
        <tr class="{{ $zeile['arbeitstag'] ? '' : 'frei' }}">
            <td>{{ $zeile['date']->locale('de')->isoFormat('dd, DD.MM.') }}</td>
            <td>
                @foreach($zeiten as $z)
                    {{ $z->start?->format('H:i') }}–{{ $z->end?->format('H:i') ?? '…' }}@if(!$loop->last), @endif
                @endforeach
            </td>
            <td class="num">{{ $zeiten->sum('pause') > 0 ? $zeiten->sum('pause').' Min' : '' }}</td>
            <td class="num">
                @if($zeile['soll'] > 0 || $zeile['ist'] > 0){{ $hm($zeile['ist']) }} / {{ $hm($zeile['soll']) }}@endif
            </td>
            <td>{{ \Illuminate\Support\Str::limit($bemerkungen->implode(', '), 40) }}</td>
            <td class="num {{ $zeile['diff'] < 0 ? 'minus' : 'plus' }}">
                @if($zeile['zaehlt'] && ($zeile['soll'] > 0 || $zeile['ist'] > 0)){{ $hm($zeile['diff'], true) }}@endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="unten">
    <div style="width: 50%;">
        <table class="summen">
            <tr><th>Stundenkonto Vormonat</th><td class="num">{{ $hm($saldoVorher) }} h</td></tr>
            <tr><th>Stundenkonto Monatsende</th><td class="num">{{ $hm($timesheet->working_time_account) }} h ({{ $hm($timesheet->working_time_account - $saldoVorher, true) }} h)</td></tr>
            <tr><th>Urlaub bisher im Jahr</th><td class="num">{{ $fmt($timesheet->holidays_old) }}</td></tr>
            <tr><th>Urlaub in diesem Monat</th><td class="num">{{ $fmt($timesheet->holidays_new) }}</td></tr>
            <tr><th>Resturlaub</th><td class="num">{{ $fmt($timesheet->holidays_rest) }}</td></tr>
        </table>
    </div>
    <div class="unterschrift">
        <div class="linie"></div>
        <div>Unterschrift Mitarbeiter/in</div>
        <div class="linie" style="margin-top: 18px;"></div>
        <div>Unterschrift Leitung</div>
    </div>
</div>
</body>
</html>
