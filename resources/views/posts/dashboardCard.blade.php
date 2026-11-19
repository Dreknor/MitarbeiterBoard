<div class="card">
    <div class="card-header bg-gradient-directional-blue text-white">
        <h5>
            @if($darfPosten)
                <div class="d-inline pull-right">
                    <a href="{{ route('posts.create') }}" class="btn btn-sm btn-bg-gradient-x-blue-green">
                        <i class="fa fa-plus"></i>
                    </a>
                </div>
            @endif
            Nachrichten
        </h5>
    </div>
    <div class="card-body">
        <div class="container-fluid">
            <div class="row">
                @if($posts->count() > 0)
                    <div class="container-fluid">
                        @foreach($posts as $post)
                            @include('posts.post')
                        @endforeach
                    </div>
                @else
                    <p>
                        Keine Nachrichten aktiv
                    </p>
                @endif
            </div>

        </div>
    </div>
</div>
