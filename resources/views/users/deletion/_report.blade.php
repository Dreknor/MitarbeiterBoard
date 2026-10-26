{{-- Auswirkungsübersicht: was mit welchen Datensätzen passiert --}}
@if(empty($report))
    <p class="text-muted">Keine Datensätze betroffen.</p>
@else
    @foreach($report as $area => $rows)
        <h6 class="mt-3">{{ $area }}</h6>
        <table class="table table-sm table-striped">
            <thead>
                <tr>
                    <th>Daten</th>
                    <th class="text-right" style="width: 110px">Datensätze</th>
                    <th style="width: 220px">Behandlung</th>
                    <th style="width: 140px">Frist bis</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>
                            {{ $row['label'] }}
                            <small class="text-muted d-block">{{ $row['table'] }}</small>
                        </td>
                        <td class="text-right">{{ $row['count'] }}</td>
                        <td>{{ $row['action'] }}</td>
                        <td>{{ $row['until']?->format('d.m.Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
@endif

@if(!empty($externalSteps))
    <div class="alert alert-info mt-3">
        <strong>Schritte außerhalb des Boards</strong>
        <ul class="mb-0">
            @foreach($externalSteps as $step)
                <li>{{ $step }}</li>
            @endforeach
        </ul>
    </div>
@endif
