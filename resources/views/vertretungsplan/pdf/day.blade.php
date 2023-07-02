@if(!$x->isWeekend())
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body ">
                    <h5>
                        Vertretungen für <div class="text-danger d-inline">{{$x->locale('de')->dayName}}</div>, den {{$x->format('d.m.Y')}} @if($weeks->count() > 0 and $weeks->where('week', $x->copy()->startOfWeek())->first() != null) ({{$weeks->where('week', $x->copy()->startOfWeek())->first()->type}} - Woche) @endif
                    </h5>
                </div>
                <div>
                    <div class="card-body">
                        <div class="">
                            <table class="table table-bordered table-striped">
                                <thead  class="thead-light">
                                <tr class="">
                                    <th class="d-lg-table-cell">Klasse</th>
                                    <th class="d-lg-table-cell">Stunde</th>
                                    <th class="d-lg-table-cell">Fächer</th>
                                    <th class="d-lg-table-cell">Lehrer</th>
                                    <th class="d-lg-table-cell">Kommentar</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($vertretungen->sortBy('klasse.name')->filter(function ($vertretung) use ($x) {
                                    if ($vertretung->date->eq($x)){
                                        return $vertretung;
                                    }
                                }) as $vertretung)
                                    <tr @if(($loop->iteration-1)%2 != 0) class="bg-grey" @endif>
                                        <td class="d-lg-table-cell">{{$vertretung->klasse->name}}</td>
                                        <td class="d-lg-table-cell">{{$vertretung->stunde}}</td>
                                        <td class="d-lg-table-cell">{{$vertretung->altFach}} @if($vertretung->neuFach) -> {{$vertretung->neuFach}}@endif</td>
                                        <td class="d-lg-table-cell">{{optional($vertretung->lehrer)->shortname}}</td>
                                        <td class="d-lg-table-cell">{{$vertretung->comment}}</td>
                                    </tr>
                                @endforeach
                                <tr class="">

                                </tr>
                                @foreach($news->filter(function ($news) use ($x) {
                                    if ($news->isActive($x)){
                                         return $news;
                                    }
                                }) as $dailyNews)
                                    <tr>
                                        <th colspan="6" class="border-outline-info">
                                            {{$dailyNews->news}}
                                        </th>
                                    </tr>
                                @endforeach
                                @if(!is_null($absences))
                                    <tr>
                                        <th colspan="6">
                                            @if($absences->count() > 1)
                                                Es fehlen:
                                            @else
                                                Es fehlt:
                                            @endif
                                            @foreach($absences->filter(function ($absence) use ($x) {
                                                if ($absence->start->lte($x) and $absence->end->gte($x)){
                                                    return $absence;
                                                }
                                            }) as $absence)
                                                {{$absence->user->shortname}}@if(!$loop->last),@endif
                                            @endforeach
                                        </th>
                                    </tr>
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endif
