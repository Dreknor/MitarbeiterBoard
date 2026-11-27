{{-- Karten eines Feldes im Ergebnisboard-PDF: $liste, $f (Farbwerte), $raster (1|2), $leer (Text oder null) --}}
@php
    $liste = collect($liste)->values();
    $zeilen = [];
    if ((int) $raster === 2) {
        $zeile = [];
        foreach ($liste as $k) {
            if (! empty($k['breit'])) {
                if ($zeile) { $zeilen[] = $zeile; $zeile = []; }
                $zeilen[] = [$k];
                continue;
            }
            $zeile[] = $k;
            if (count($zeile) === 2) { $zeilen[] = $zeile; $zeile = []; }
        }
        if ($zeile) { $zeilen[] = $zeile; }
    } else {
        $zeilen = $liste->map(fn ($k) => [$k])->all();
    }
@endphp
@if($liste->isEmpty())
    @if($leer)<div class="leer">{{ $leer }}</div>@endif
@else
    <table class="karten {{ (int) $raster === 2 ? 'zwei' : '' }}">
        @if((int) $raster === 2)<colgroup><col style="width: 50%"><col style="width: 50%"></colgroup>@endif
        @foreach($zeilen as $zeile)
            <tr>
                @foreach($zeile as $k)
                    <td @if((int) $raster === 2 && (count($zeile) === 1 && ! empty($k['breit']))) colspan="2" @endif>
                        <div class="karte {{ $k['fokus'] ? 'fokus' : '' }}" style="{{ $k['fokus'] ? '' : 'border-color: '.$f['rand'].';' }}">
                            @if($k['etikett'])<span class="etikett" style="background: {{ $f['flaeche'] }}; color: {{ $f['hinweis'] }};">{{ $k['etikett'] }}</span><br>@endif
                            <span class="karte-titel">@if($k['fokus'])<span class="stern">★</span> @endif{{ $k['titel'] }}</span>
                            @if($k['zusatz'])<div class="karte-zusatz">{!! nl2br(e($k['zusatz'])) !!}</div>@endif
                            @php
                                $meta = [];
                                if ($k['faellig_am']) {
                                    $meta[] = '<span class="'.($k['ueberfaellig'] ? 'ueberfaellig' : '').'">fällig '.e(\Illuminate\Support\Carbon::parse($k['faellig_am'])->format('d.m.Y')).'</span>';
                                }
                                if (! empty($k['zustaendige'])) {
                                    $meta[] = e(collect($k['zustaendige'])->pluck('name')->join(', '));
                                }
                                if (! empty($k['anhaenge'])) {
                                    $meta[] = count($k['anhaenge']).' '.(count($k['anhaenge']) === 1 ? 'Anhang' : 'Anhänge');
                                }
                                if ($k['aufgabe']) {
                                    $meta[] = $k['aufgabe']['erledigt']
                                        ? '<span class="erledigt">✓ erledigt</span>'
                                        : 'Aufgabe offen'.($k['aufgabe']['fortschritt'] ? ' ('.e($k['aufgabe']['fortschritt']).')' : '');
                                }
                            @endphp
                            @if($meta)<div class="karte-meta">{!! implode(' · ', $meta) !!}</div>@endif
                        </div>
                    </td>
                @endforeach
                @if((int) $raster === 2 && count($zeile) === 1 && empty($zeile[0]['breit']))<td></td>@endif
            </tr>
        @endforeach
    </table>
@endif
