<div class="card">
    <div class="card-header">
        <h6>Dienstplan</h6>
    </div>
    <div class="card-body">
        @foreach($rosters as $roster)
            <div class="row">
                <div class="col">
                    <b>
                        {{$roster->department->name}}:
                    </b>
                </div>
                <div class="col">
                    <div class="pull-left">
                        <a href="{{route('roster.export.pdf', $roster->id)}}">Dienstplan anzeigen</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
