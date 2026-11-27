{{-- Ein Baustein des angepassten Arbeitsblatts (siehe pdf/arbeitsblatt.blade.php, App\Support\AbStruktur) --}}
@php
    $inhalt = function (array $segmente) use ($lueckeBreite) {
        $html = '';
        foreach ($segmente as $s) {
            if (($s['typ'] ?? '') === 'luecke') {
                $html .= '<span class="ab-luecke" style="width: '.$lueckeBreite((int) $s['laenge']).'pt"></span>';
            } else {
                $html .= nl2br(e($s['text'] ?? ''), false);
            }
        }
        return $html;
    };
@endphp
@switch($b['typ'])
    @case('ueberschrift')
        <h1 class="ab-baustein">{{ $b['text'] }}</h1>
        @break

    @case('anweisung')
        <p class="ab-baustein ab-anweisung">{!! nl2br(e($b['text']), false) !!}</p>
        @break

    @case('text')
        <p class="ab-baustein">{!! $inhalt($b['inhalt'] ?? []) !!}</p>
        @break

    @case('aufgabe')
        <div class="ab-baustein ab-aufgabe">
            <div>@if(($b['nummer'] ?? '') !== '')<span class="ab-nummer">{{ $b['nummer'] }}</span>@endif{!! $inhalt($b['inhalt'] ?? []) !!}</div>
            @if(!empty($b['kinder']))
                <div class="ab-kinder">
                    @foreach($b['kinder'] as $kind)
                        @include('pdf.arbeitsblatt-baustein', ['b' => $kind])
                    @endforeach
                </div>
            @endif
        </div>
        @break

    @case('ankreuzen')
        <div class="ab-baustein">
            @if(($b['frage'] ?? '') !== '')<p style="margin: 0">{!! nl2br(e($b['frage']), false) !!}</p>@endif
            @foreach($b['optionen'] ?? [] as $option)
                <div class="ab-option"><span class="ab-kaestchen{{ !empty($b['mehrfach']) ? '' : ' rund' }}"></span>{{ $option }}</div>
            @endforeach
        </div>
        @break

    @case('tabelle')
        <table class="ab-baustein ab-tabelle">
            @foreach($b['zeilen'] ?? [] as $i => $zeile)
                <tr>
                    @foreach($zeile as $zelle)
                        @if($i === 0 && !empty($b['kopfzeile']))<th>{{ $zelle }}</th>@else<td>{{ $zelle }}</td>@endif
                    @endforeach
                </tr>
            @endforeach
        </table>
        @break

    @case('schreiblinien')
        <div class="ab-baustein ab-schreiblinien">
            @for($i = 0; $i < (int) ($b['anzahl'] ?? 3); $i++)<div class="ab-linie"></div>@endfor
        </div>
        @break

    @case('bild')
        @php $quelle = !empty($b['media_id']) ? $bild((int) $b['media_id']) : null; @endphp
        <div class="ab-baustein ab-bild">
            @if($quelle)
                <img src="{{ $quelle }}" alt="{{ $b['beschreibung'] ?? '' }}" style="width: {{ $bildBreite($b) }}%">
            @else
                <div class="ab-bild-fehlt">Abbildung{{ ($b['beschreibung'] ?? '') !== '' ? ': '.$b['beschreibung'] : '' }}</div>
            @endif
        </div>
        @break
@endswitch
