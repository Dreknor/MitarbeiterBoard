{{--
    Hilfe „Was wirkt sich wo aus?“
    Ohne $keys: vollständige Übersicht als aufklappbares Panel.
    Mit $keys (Array): kurze Hinweiszeile unter einem Formularfeld.
--}}
@php
    $wirkung = [
        'hours' => [
            'Wochenstunden / Stellenanteil',
            ['Arbeitszeitnachweis: Soll je Woche = Stellenanteil × Vollzeit-Stunden (Einstellung), verteilt auf die Arbeitstage',
             'Dienstplan: Auslastung und automatische Planung',
             'Hort-Planung: Übernahme der Anstellungen'],
        ],
        'workdays' => [
            'Arbeitstage',
            ['Arbeitszeitnachweis: Soll fällt nur an diesen Wochentagen an',
             'Urlaub: es zählen nur diese Tage; bei anteiliger Berechnung auch der Urlaubsanspruch'],
        ],
        'period' => [
            'Beginn / Ende',
            ['Arbeitszeitnachweis: Soll gilt tagesgenau nur innerhalb des Zeitraums',
             'Rückwirkende Änderung: bereits abgeschlossene Monate werden zur Prüfung markiert (siehe Änderungsverlauf)',
             'Wiedervorlage bei befristeten Verträgen (vor Ablauf)'],
        ],
        'department' => [
            'Bereich',
            ['Sichtbarkeit: Bereichsleitungen sehen nur Personen ihrer Bereiche',
             'Dienstplan des Bereichs und Nextcloud-Ablage (Ordner nach primärem Bereich)',
             'Fortbildungs-Freigaben'],
        ],
        'status' => [
            'Status (aktiv / ruhend / beendet)',
            ['Ruhend (z. B. Elternzeit): zählt nicht für Soll-Arbeitszeit und wird bei der Dienstplan-Planung nicht berücksichtigt',
             'Beendet: Nextcloud-Ordner wird archiviert, Offboarding startet (wenn keine weitere Anstellung besteht), Aufbewahrungsfrist beginnt'],
        ],
        'type' => [
            'Anstellungsart',
            ['Pflicht-Qualifikationen richten sich nach der Art (z. B. Lehrkraft)',
             'Lehrkraft: Deputat, Schulart und Wochenstunden-Berechnung'],
        ],
        'contract' => [
            'Vertragsart / Befristung',
            ['Prüfung der Befristungskette nach § 14 TzBfG (Warnhinweis beim Speichern)',
             'Wiedervorlage vor Vertragsende; Vertrag wird nach Ablauf automatisch beendet'],
        ],
        'probation' => [
            'Probezeit',
            ['Wiedervorlage an die Personalverwaltung vor Ablauf der Probezeit'],
        ],
        'salary' => [
            'Vergütung',
            ['Nur mit Recht „Gehalt ansehen/bearbeiten“ sichtbar und änderbar – keine Auswirkung auf Zeit- und Urlaubsmodule'],
        ],
        'new' => [
            'Neue Anstellung',
            ['Erste Anstellung einer Person: Onboarding-Prozess (falls Vorlage eingestellt), Nextcloud-Ordner, fehlende Pflicht-Qualifikationen werden angelegt',
             '„Ersetzt Anstellung“: die alte Anstellung endet am Vortag – ohne Offboarding'],
        ],
    ];
@endphp

@if(isset($keys))
    @foreach($keys as $k)
        @if(isset($wirkung[$k]))
        <details class="mt-1 text-xs text-gray-500">
            <summary class="cursor-pointer select-none text-blue-700">ⓘ Wirkt sich aus auf …</summary>
            <ul class="list-disc list-inside mt-1 space-y-0.5">
                @foreach($wirkung[$k][1] as $zeile)<li>{{ $zeile }}</li>@endforeach
            </ul>
        </details>
        @endif
    @endforeach
@else
    <details class="personal-card mb-6 group">
        <summary class="cursor-pointer select-none font-semibold text-gray-700">
            ⓘ Was wirkt sich wo aus?
            <span class="text-xs font-normal text-gray-500 ml-2">Zusammenhänge zwischen Personalakte, Verträgen und den übrigen Modulen</span>
        </summary>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left py-2 pr-4 text-gray-500 font-medium w-1/4">Angabe / Aktion</th>
                        <th class="text-left py-2 text-gray-500 font-medium">Wirkung</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($wirkung as $eintrag)
                    <tr class="border-b border-gray-50 align-top">
                        <td class="py-2 pr-4 font-medium text-gray-800">{{ $eintrag[0] }}</td>
                        <td class="py-2 text-gray-600">
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach($eintrag[1] as $zeile)<li>{{ $zeile }}</li>@endforeach
                            </ul>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 mt-3">
            Jede Änderung wird im <strong>Änderungsverlauf</strong> der Personalakte protokolliert (wer, wann, alt → neu).
        </p>
    </details>
@endif
