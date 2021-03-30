<div class="container-fluid">
    <div class="row">
        <div class="card">
            <div class="card-header">
                Meine Vertretungen
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Stunde</th>
                        <th>Klasse</th>
                        <th>zu erteilendes Fach</th>
                        <th>Kommentar</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach(auth()->user()->vertretungen()->whereDate('date', '>=', \Carbon\Carbon::today())->orderBy('date')->orderBy('stunde')->get() as $vertretung)
                        <tr>
                            <td>{{$vertretung->date->format('d.m.Y')}}</td>
                            <td>{{$vertretung->stunde}}</td>
                            <td>{{$vertretung->klasse->name}}</td>
                            <td>{{$vertretung->neuFach}}</td>
                            <td>{{$vertretung->comment}}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
