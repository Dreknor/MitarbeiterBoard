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
</div>
