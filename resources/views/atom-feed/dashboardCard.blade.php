<div class="card">
    <div class="card-header bg-gradient-directional-blue text-white">
        <div class="pull-right">
            <a href="#" class="text-white btn btn-link" onclick="disableCard({{$card->id}})">
                X
            </a>
        </div>
        <h5>
            <i class="fas fa-rss mr-1"></i>
            {{ $card->title }}
        </h5>
    </div>
    <div class="card-body p-0">
        @if(empty($atomFeedEntries))
            <div class="p-3 text-muted text-center">
                <i class="fas fa-exclamation-circle"></i>
                Feed konnte nicht geladen werden oder enthält keine Einträge.<br>
                <small>URL: <a href="{{ $atomFeedUrl }}" target="_blank" rel="noopener">{{ $atomFeedUrl }}</a></small>
            </div>
        @else
            <ul class="list-group list-group-flush">
                @foreach($atomFeedEntries as $entry)
                    <li class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div style="min-width:0">
                                @if(!empty($entry['link']))
                                    <a href="{{ $entry['link'] }}" target="_blank" rel="noopener"
                                       class="font-weight-bold text-dark d-block text-truncate"
                                       title="{{ $entry['title'] }}">
                                        {{ $entry['title'] }}
                                    </a>
                                @else
                                    <span class="font-weight-bold d-block text-truncate"
                                          title="{{ $entry['title'] }}">
                                        {{ $entry['title'] }}
                                    </span>
                                @endif
                                @if(!empty($entry['summary']))
                                    <small class="text-muted">
                                        {{ \Illuminate\Support\Str::limit($entry['summary'], 120) }}
                                    </small>
                                @endif
                            </div>
                            @if(!empty($entry['published']))
                                <small class="text-muted ml-2 text-nowrap">
                                    {{ $entry['published']->format('d.m.Y') }}
                                </small>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    <div class="card-footer text-right py-1">
        <small>
            <a href="{{ $atomFeedUrl }}" target="_blank" rel="noopener" class="text-muted">
                <i class="fas fa-rss"></i> Feed öffnen
            </a>
            &nbsp;|&nbsp;
            <a href="{{ route('employes.self') }}#atom-feed-settings" class="text-muted">
                <i class="fas fa-cog"></i> Einstellungen
            </a>
        </small>
    </div>
</div>

