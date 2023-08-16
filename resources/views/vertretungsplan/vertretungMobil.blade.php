@for($x=Carbon\Carbon::today(); $x< $targetDate; $x->addDay())
    @if(!$x->isWeekend())
        <div class="card border border-dark">
            <div class="card-header" id="heading{{$x->format('Ymd')}}">
                <h6>
                    Vertretungen für <div class="text-danger d-inline">{{$x->locale('de')->dayName}} </div>, den {{$x->format('d.m.Y')}}
                </h6>
            </div>
            <div id="collapse{{$x->format('Ymd')}}"  aria-labelledby="heading{{$x->format('Ymd')}}" >
                <div class="card-body">
                        <table class="table table-bordered table-sm table-responsive-sm">
                            <thead  class="thead-light">
                            <tr class="">
                                <th class="">Klasse</th>
                                <th class="">Stunde</th>
                                <th class="">Fächer</th>
                                <th class="">Lehrer</th>
                                <th class="">Kommentar</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($vertretungen->filter(function ($vertretung) use ($x) {
                                if ($vertretung->date->eq($x)){
                                    return $vertretung;
                                }
                            }) as $vertretung)
                                <tr @if(($loop->iteration-1)%2 == 0) class="bg-secondary text-white" @endif>
                                    <td class="">
                                        {{\Illuminate\Support\Str::after($vertretung->klasse->name, ' ')}}
                                    </td>
                                    <td >
                                        {{$vertretung->stunde}}
                                    </td>
                                    <td>
                                        {{\Illuminate\Support\Str::limit($vertretung->altFach, 5)}} @if($vertretung->neuFach) -> {{\Illuminate\Support\Str::limit($vertretung->neuFach,5)}}@endif
                                    </td>
                                    <td >
                                        @if(!is_null($vertretung->lehrer))
                                            {{\Illuminate\Support\Str::limit(\Illuminate\Support\Str::after($vertretung->lehrer->name, ' '), config('config.short_teachers_name') ,'...')}}
                                        @endif
                                    </td>
                                    <td >
                                        {{$vertretung->comment}}
                                    </td>
                                </tr>

                            @endforeach
                            <tr class="">

                            </tr>
                            @foreach($news->filter(function ($news) use ($x) {
                                if (($news->date_start->eq($x) and $news->date_end == null) or ($news->date_start->lessThanOrEqualTo($x) and $news->date_end != null and $news->date_end->greaterThanOrEqualTo($x))){
                                    return $news;
                                }
                            }) as $dailyNews)
                                <tr>
                                    <th colspan="6" class="border-outline-info">
                                        {{$dailyNews->news}}
                                    </th>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                </div>
            </div>
        </div>
    @endif
@endfor
