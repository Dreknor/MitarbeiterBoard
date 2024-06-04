<div class="card">
    <div class="card-header">
        @cannot('view vertretungen')Meine @endcan Vertretungen
    </div>
    <div class="card-body">
        <table class="table table-bordered table-striped ">
            <thead>
            <tr>
                <th>Datum</th>
                @can('view vertretungen')
                    <th>Lehrer</th>
                @endcan
                <th>Stunde</th>
                <th>Klasse</th>
                <th>zu erteilendes Fach</th>
                <th>Kommentar</th>
            </tr>
            </thead>
            <tbody>
            @foreach($vertretungen as $vertretung)
                <tr>
                    <td>{{$vertretung->date->format('d.m.Y')}}</td>
                    @can('view vertretungen')
                        <td>{{$vertretung->lehrer->name}}</td>
                    @endcan
                    <td>{{$vertretung->stunde}}</td>
                    <td>{{$vertretung->klasse->name}}</td>
                    <td>{{$vertretung->neuFach}}</td>
                    <td>{{$vertretung->comment}}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead>
            <tr>
                <th>
                    Woche
                </th>
                <th>

                </th>
            </tr>
            </thead>
            <tbody>
            @foreach(\App\Models\VertretungsplanWeek::where('week', '>=', \Carbon\Carbon::today()->startOfWeek())->take(3)->get() as $week)
                <tr>
                    <td>
                        {{$week->date}}
                    </td>
                    <td>
                        {{$week->type}} - Woche
                    </td>
                </tr>
            @endforeach
            </tbody>

        </table>
    </div>
</div>

