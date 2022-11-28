<div class="card border border-left-0">
    <div class="card-header">
        Merkliste
    </div>
    <div class="card-body">
        <ul class="list-group">
            @foreach($events->where('employe_id', null)->where('date', $day->format('Y-m-d')) as $event)
                <li class="list-group-item Termin"
                    id="task_{{$event->id}}"
                    data-id="{{$event->id}}"
                    data-start="{{$event->start->format('H:i')}}"
                    data-end="{{$event->end->format('H:i')}}"
                    data-date="{{$event->date}}"
                    data-event="{{$event->event}}"
                    data-employe="{{$event->employe_id}}">
                    {{$event->event}} ({{$event->start->format('H:i')}})
                </li>
            @endforeach
        </ul>
    </div>
</div>
