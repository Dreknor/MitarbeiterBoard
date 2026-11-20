{{--
    Gruppierte Zeilen des Geburtstags-Imports.
    $gruppen: Status => Zeilen, $bericht: true nach dem Übernehmen (andere Beschriftung)
--}}
@php
    $Import = \App\Services\Personal\GeburtstagImportService::class;

    $beschriftung = $Import::STATUS;
    if ($bericht ?? false) {
        $beschriftung[$Import::SETZEN] = 'Gesetzt';
    }
    $farben = [
        $Import::SETZEN         => 'bg-green-100 text-green-800',
        $Import::ABWEICHEND     => 'bg-amber-100 text-amber-800',
        $Import::KONFLIKT       => 'bg-red-100 text-red-800',
        $Import::UNGUELTIG      => 'bg-red-100 text-red-800',
        $Import::NICHT_GEFUNDEN => 'bg-red-100 text-red-800',
        $Import::GLEICH         => 'bg-gray-100 text-gray-700',
        $Import::DOPPELT        => 'bg-gray-100 text-gray-700',
    ];
    // Unauffällige Gruppen standardmäßig eingeklappt
    $offen = [$Import::SETZEN, $Import::ABWEICHEND, $Import::KONFLIKT, $Import::UNGUELTIG, $Import::NICHT_GEFUNDEN];
    $datum = fn (?string $d) => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '–';
@endphp

<div class="flex flex-col gap-3">
    @foreach($gruppen as $status => $zeilen)
        @continue(count($zeilen) === 0)
        <details class="uim-card overflow-hidden" @if(in_array($status, $offen, true)) open @endif>
            <summary class="flex items-center justify-between gap-3 px-4 py-3">
                <span class="font-semibold text-gray-800">{{ $beschriftung[$status] }}</span>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $farben[$status] }}">{{ count($zeilen) }}</span>
            </summary>
            <div class="overflow-x-auto">
                <table class="uim-table">
                    <thead>
                        <tr>
                            <th>Zeile</th>
                            <th>E-Mail</th>
                            <th>Person</th>
                            <th>Wert in der Datei</th>
                            @if(in_array($status, [$Import::ABWEICHEND, $Import::GLEICH], true))
                                <th>Gespeichert</th>
                            @endif
                            <th>Hinweis</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($zeilen as $z)
                            <tr>
                                <td class="text-gray-500 tabular-nums">{{ $z['zeile'] }}</td>
                                <td>{{ $z['email'] ?: '–' }}</td>
                                <td>{{ $z['name'] ?? '–' }}</td>
                                <td class="tabular-nums">
                                    @if($z['datum'])
                                        <span class="font-medium">{{ $datum($z['datum']) }}</span>
                                    @else
                                        {{ $z['wert'] !== '' ? $z['wert'] : '–' }}
                                    @endif
                                </td>
                                @if(in_array($status, [$Import::ABWEICHEND, $Import::GLEICH], true))
                                    <td class="tabular-nums">{{ $datum($z['vorhanden']) }}</td>
                                @endif
                                <td class="text-gray-600">{{ $z['hinweis'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endforeach
</div>
