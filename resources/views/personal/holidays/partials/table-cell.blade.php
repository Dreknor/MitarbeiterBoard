
@php
    $cellData = getHolidayCellData($holiday, $day);
@endphp

<td class="{{ $cellData['class'] }} border-right text-center">
    {!! $cellData['icon'] !!}
</td>
