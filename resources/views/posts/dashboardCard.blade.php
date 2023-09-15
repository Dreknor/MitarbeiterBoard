<div class="card">
    <div class="card-header bg-gradient-directional-blue text-white">
        <h5>
            @can('create posts')
                <div class="d-inline pull-right">
                    <a href="{{url('posts/create')}}" class="btn btn-sm btn-bg-gradient-x-blue-green">
                        <i class="fa fa-plus"></i>
                    </a>
                </div>
            @endcan
            Nachrichten
        </h5>
    </div>
    <div class="card-body">
        <div class="container-fluid">
            <div class="row">
                @if($posts->count() > 0)
                    <div class="container-fluid">
                        @foreach($posts as $post)
                            @if($post->released == 1 or $post->author_id == auth()->id())
                                @include('posts.post')
                            @endif
                        @endforeach
                    </div>
                @else
                    <p>
                        Keine Nachrichten aktiv
                    </p>
                @endif
            </div>
            <div class="row">
                <div class="container-fluid">
                    {{$posts->links()}}
                </div>
            </div>

        </div>
    </div>
</div>
