<div class="card">
    <div class="card-header bg-gradient-directional-blue text-white">
        <h5>
            {{$card->title}} (zuletzt bearbeitet)
        </h5>
    </div>
    <div class="card-body">
        <div class="container-fluid">
            <ul class="list-group">
                @foreach($sites as $site)
                    <li class="list-group-item">
                        <a href="{{ route('wiki', $site->slug) }}">
                            {{ $site->title }}
                        </a>
                        <span class="float-right">
                            {{ $site->updated_at->diffForHumans() }}
                        </span>
                @endforeach
            </ul>
        </div>
    </div>
</div>
