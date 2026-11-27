{{-- Ein Hauptfeld im Ergebnisboard-PDF: $b (Bereich), $f (Farbwerte), $karten, $unter, $farbe --}}
<div class="feld" style="background: {{ $f['flaeche'] }}; border-color: {{ $f['rand'] }};">
    <div class="feld-kopf" style="border-color: {{ $f['rand'] }};">
        @if(!empty($b['badge']))<span class="feld-badge" style="background: {{ $f['badge'] }}; color: {{ $f['text'] }};">{{ $b['badge'] }}</span>@endif
        <p class="feld-titel" style="color: {{ $f['text'] }};">{{ $b['titel'] }}</p>
        @if(!empty($b['hinweis']))<p class="feld-hinweis" style="color: {{ $f['hinweis'] }};">{{ $b['hinweis'] }}</p>@endif
    </div>

    @include('pdf.partials.ergebnisboard_karten', ['liste' => $karten->get($b['schluessel'], collect()), 'f' => $f, 'raster' => $b['raster'] ?? 1, 'leer' => $unter->has($b['schluessel']) ? null : 'Keine Karten'])

    @foreach($unter->get($b['schluessel'], []) as $u)
        @php($uf = $farbe($u['farbe'] ?? $b['farbe']))
        <div class="unter" style="background: {{ $uf['stark'] }}; border-color: {{ $uf['rand'] }};">
            <div class="unter-titel" style="color: {{ $uf['text'] }};">
                <span class="punkt" style="background: {{ $uf['punkt'] }}; margin-left: 0;"></span>{{ $u['titel'] }}
            </div>
            @if(!empty($u['hinweis']))<div class="feld-hinweis" style="color: {{ $uf['hinweis'] }}; margin-bottom: .5mm;">{{ $u['hinweis'] }}</div>@endif
            @include('pdf.partials.ergebnisboard_karten', ['liste' => $karten->get($u['schluessel'], collect()), 'f' => $uf, 'raster' => $u['raster'] ?? 1, 'leer' => 'Keine Karten'])
        </div>
    @endforeach
</div>
