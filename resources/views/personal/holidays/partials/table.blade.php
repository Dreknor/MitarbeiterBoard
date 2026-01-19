@php
    // Performance-Optimierung: Berechne alle Tage vorher
    $days = [];
    for($x = $startOfTable->copy(); $x->lessThanOrEqualTo($endOfTable); $x->addDay()) {
        $days[] = [
            'date' => $x->copy(),
            'day' => $x->day,
            'isWeekend' => $x->isWeekend(),
            'isHoliday' => is_holiday($x),
        ];
    }
@endphp

<style>
    .holiday-table {
        font-size: 0.85rem;
        margin-bottom: 20px;
    }
    .holiday-table th {
        padding: 8px 4px;
        white-space: nowrap;
        background-color: #007bff;
        color: white;
        border: 1px solid #0056b3;
        min-width: 30px;
        text-align: center;
    }
    .holiday-table th.weekend-header {
        background-color: #6c757d;
    }
    .holiday-table td {
        padding: 8px 4px;
        border: 1px solid #dee2e6;
        text-align: center;
        min-width: 30px;
        height: 35px;
    }
    .holiday-table td.user-name {
        position: sticky;
        left: 0;
        background-color: white;
        font-weight: 500;
        text-align: left;
        min-width: 150px;
        z-index: 1;
    }
    .holiday-table td.weekend-day {
        background-color: #e9ecef;
    }
    .holiday-table td.ferien-day {
        background-color: #cfe2ff;
    }
    .holiday-table td.approved-holiday {
        background-color: #28a745;
        color: white;
    }
    .holiday-table td.pending-holiday {
        background-color: #ffc107;
        color: white;
    }
    .holiday-table td.rejected-holiday {
        background-color: #dc3545;
        color: white;
    }
    .holiday-table-wrapper {
        overflow-x: auto;
        margin-bottom: 20px;
    }
</style>

<div class="holiday-table-wrapper">
    <table class="table table-bordered table-sm holiday-table">
        <thead>
            <tr>
                <th style="position: sticky; left: 0; z-index: 2;">Name</th>
                @foreach($days as $dayInfo)
                    <th class="{{ ($dayInfo['isWeekend'] || $dayInfo['isHoliday']) ? 'weekend-header' : '' }}">
                        {{ $dayInfo['day'] }}
                        @if($dayInfo['isWeekend'] || $dayInfo['isHoliday'])
                            <br><i class="fas fa-moon" style="font-size: 0.7rem;"></i>
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
                @php
                    // Performance-Optimierung: Hole nur die Holidays für diesen User aus der Map
                    $userHolidays = $holidayMap[$user->id] ?? [];
                    $groupClasses = '';
                    if($user->groups_rel) {
                        foreach($user->groups_rel as $group) {
                            $groupClasses .= $group->name . ' ';
                        }
                    }
                @endphp
                <tr class="{{ trim($groupClasses) }}" data-user-id="{{ $user->id }}">
                    <td class="user-name">{{ $user->name }}</td>

                    @foreach($days as $dayInfo)
                        @php
                            $currentDay = $dayInfo['date'];
                            $isWeekendOrHoliday = $dayInfo['isWeekend'] || $dayInfo['isHoliday'];

                            // Finde passendes Holiday
                            $holiday = null;
                            foreach($userHolidays as $h) {
                                if ($h->start_date->lessThanOrEqualTo($currentDay) &&
                                    $h->end_date->greaterThanOrEqualTo($currentDay)) {
                                    $holiday = $h;
                                    break;
                                }
                            }

                            // Bestimme Styling
                            $cellClass = '';
                            $icon = '';
                            $title = '';

                            // Prüfe Ferien - is_ferien gibt ein Array/Object zurück, nicht nur true/false
                            $ferienInfo = is_ferien($currentDay);
                            $isFerien = !is_null($ferienInfo);

                            if ($isWeekendOrHoliday) {
                                $cellClass = 'weekend-day';
                            } elseif ($isFerien) {
                                $cellClass = 'ferien-day';
                                if (is_array($ferienInfo)) {
                                    $title = $ferienInfo['name'] ?? 'Ferien';
                                } elseif (is_object($ferienInfo)) {
                                    $title = $ferienInfo->name ?? 'Ferien';
                                }
                            }

                            if ($holiday && !$isWeekendOrHoliday) {
                                $title = $holiday->employe->name . ': ' . $holiday->start_date->format('d.m.Y') . ' - ' . $holiday->end_date->format('d.m.Y');
                                if ($holiday->approved) {
                                    $cellClass = 'approved-holiday';
                                    $icon = '<i class="fas fa-check"></i>';
                                } elseif (!$holiday->approved && !$holiday->rejected) {
                                    $cellClass = 'pending-holiday';
                                    $icon = '<i class="fas fa-question"></i>';
                                } elseif ($holiday->rejected) {
                                    $cellClass = 'rejected-holiday';
                                    $icon = '<i class="fas fa-times"></i>';
                                }
                            }
                        @endphp
                        <td class="{{ $cellClass }}" @if($title) title="{{ $title }}" @endif>
                            {!! $icon !!}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<!-- Legende -->
<div class="mt-3 mb-4">
    <div class="d-flex flex-wrap align-items-center">
        <div class="mr-4 mb-2">
            <span class="d-inline-block" style="width: 20px; height: 20px; background-color: #28a745; border-radius: 3px;"></span>
            <small class="ml-1">Genehmigt</small>
        </div>
        <div class="mr-4 mb-2">
            <span class="d-inline-block" style="width: 20px; height: 20px; background-color: #ffc107; border-radius: 3px;"></span>
            <small class="ml-1">In Prüfung</small>
        </div>
        <div class="mr-4 mb-2">
            <span class="d-inline-block" style="width: 20px; height: 20px; background-color: #dc3545; border-radius: 3px;"></span>
            <small class="ml-1">Abgelehnt</small>
        </div>
        <div class="mr-4 mb-2">
            <span class="d-inline-block" style="width: 20px; height: 20px; background-color: #e9ecef; border: 1px solid #dee2e6; border-radius: 3px;"></span>
            <small class="ml-1">Wochenende/Feiertag</small>
        </div>
        <div class="mr-4 mb-2">
            <span class="d-inline-block" style="width: 20px; height: 20px; background-color: #cfe2ff; border: 1px solid #9ec5fe; border-radius: 3px;"></span>
            <small class="ml-1">Ferien</small>
        </div>
    </div>
</div>
