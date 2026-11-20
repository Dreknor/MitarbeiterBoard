{{-- Nachricht in der (alten) Dashboard-Karte: Kurzfassung, Link zur Detailseite --}}
<div class="row">
    <div class="card w-100 @if($post->muss_bestaetigen) border-warning @endif">
        <div class="card-header bg-light">
            <h6 class="mb-1">
                <a href="{{ route('posts.show', $post) }}">{{ $post->header }}</a>
                @if(! $post->released) <span class="badge badge-warning">Entwurf</span> @endif
                @if($post->muss_bestaetigen) <span class="badge badge-warning">Bestätigung offen</span> @endif
            </h6>
            <small class="text-muted">
                {{ $post->author?->name }} · {{ $post->datum()?->format('d.m.Y H:i') }}
                @if($post->groups->isNotEmpty()) · {{ $post->groups->pluck('name')->join(', ') }} @endif
            </small>
        </div>
        @if($post->kurztext !== '' || $post->hatMehrText())
            <div class="card-body">
                {{ $post->kurztext }}
                @if($post->hatMehrText())
                    <a href="{{ route('posts.show', $post) }}">weiterlesen</a>
                @endif
            </div>
        @endif
    </div>
</div>
