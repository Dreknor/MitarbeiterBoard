<div class="card">
    <div class="card-header bg-gradient-directional-blue text-white">
        <div class="pull-right">
            <a href="#" class="text-white btn btn-link" onclick="disableCard({{ $card->id }})">X</a>
        </div>
        <h5>{{ $card->title }}</h5>
    </div>
    <div class="card-body">
        @if($favoriten->isNotEmpty())
            <h6 class="text-muted"><i class="fas fa-star text-warning"></i> Meine Favoriten</h6>
            <ul class="list-group mb-3">
                @foreach($favoriten as $site)
                    <li class="list-group-item">
                        <a href="{{ $site->url() }}">{{ $site->titel }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
        <h6 class="text-muted">Zuletzt geändert</h6>
        <ul class="list-group">
            @forelse($sites as $site)
                <li class="list-group-item">
                    <a href="{{ $site->url() }}">{{ $site->titel }}</a>
                    <span class="float-right text-muted small">{{ $site->bearbeitet_at?->diffForHumans() }}</span>
                </li>
            @empty
                <li class="list-group-item text-muted">Keine Wiki-Seiten vorhanden</li>
            @endforelse
        </ul>
        <a href="{{ route('wiki') }}" class="btn btn-link btn-block mt-2">Zum Wiki</a>
    </div>
</div>
