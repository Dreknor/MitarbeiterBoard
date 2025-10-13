<div class="card">
    <div class="card-header">
        <h6>Dienstplan</h6>
    </div>
    <div class="card-body">
        @foreach($rosters as $roster)
            <div class="row">
                <div class="col-auto">
                    <b>
                        {{$roster->department->name}}:
                    </b>
                </div>
                @can('create roster')
                    <div class="col-auto">
                        @if($roster->published)
                            <span class="badge badge-success">Veröffentlicht</span>
                        @else
                            <span class="badge badge-warning">Entwurf</span>
                        @endif
                    </div>
                @endcan
                <div class="col-auto">
                    <div class="pull-left">
                        <a href="{{route('roster.export.pdf', $roster->id)}}">Dienstplan vom {{$roster->start_date->format('d.m.Y')}} </a>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="pull-right">
                        <a href="{{route('roster.show', $roster->id)}}">
                            <i class="fa fa-edit"></i>
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @if($working_times->count() > 0)
        <div class="card-footer border-top">
            <h6>Arbeitszeiten heute</h6>
            <ul class="list-group">
                @foreach($working_times as $working_time)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{$working_time->employe->name}}:
                        <div class="d-inline " ><b>{{$working_time->start->format('H:i')}} - {{$working_time->end->format('H:i')}}</b>  </div>
                        <span class="badge badge-primary badge-pill p-2">{{$working_time->function}} </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
