<div class="card">
    <div class="card-header text-white bg-gradient-directional-blue">
        <h5>
            {{$card->title}}
        </h5>
    </div>
    <div class="card-body">
        <div class="container-fluid">
            @if(count($users) > 0)
                <ul class="list-group">
                    @foreach($users as $user => $time)
                        <li class="list-group-item">
                            {{$user}}
                            <span class="float-right">
                            Angemeldet seit:
                            {{$time}}
                        </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p>
                    Es sind keine Benutzer angemeldet.
                </p>
            @endif
        </div>
    </div>
</div>
