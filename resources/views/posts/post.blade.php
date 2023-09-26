<div class="row">
    <div class="card w-100">
        <div class="card-header bg-light">
            @if($post->author_id == auth()->id())
                <div class="d-inline pull-right ">

                    <form method="post" class="form-inline pull-right" action="{{url('posts/'.$post->id)}}">
                        @csrf
                        @method('delete')
                        @if($post->released != 1)
                            <a class="text-white btn btn-bg-gradient-x-blue-green btn-link mt-2" href="{{url('posts/'.$post->id.'/release')}}">
                                <i class="fa fa-eye"></i>
                            </a>
                        @endif
                        <a class="text-white btn btn-bg-gradient-x-orange-yellow btn-link m-4" href="{{url('posts/'.$post->id.'/edit')}}">
                            <i class="fa fa-edit"></i>
                        </a>
                        <button type="submit" class="text-white btn btn-bg-gradient-x-red-pink btn-link" >
                            <i class="fa fa-trash"></i>
                        </button>
                    </form>

                </div>
            @endif
            <h6>
                {{$post->header}} @if($post->released != 1) (unveröffentlicht) @endif
            </h6>
            <div class="row ">
                <div class="col-auto">
                    {{$post->author?->name}}
                </div>
                <div class="col-auto">
                    {{$post->created_at->format('d.m.Y H:i')}}
                </div>
            </div>
        </div>
        <div class="card-body">
            {!! $post->text !!}
        </div>
        @if($post->getMedia('files')->count() > 0)
            <div class="card-footer">
                <div class="row">
                    <div class="col-12">
                        <b>
                            Dateien
                        </b>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <ul class="list-group">
                            @foreach($post->getMedia('files')->sortBy(['name']) as $media)
                                <li class="list-group-item  list-group-item-action ">
                                    <a href="{{url('/image/'.$media->id)}}" target="_blank" class="mx-auto ">
                                        <i class="fas fa-file-download"></i>
                                        {{$media->name}} (erstellt: {{$media->created_at->format('d.m.Y H:i')}} Uhr)
                                    </a>
                                </li>
                            @endforeach

                        </ul>
                    </div>
                </div>
            </div>
        @endif
        @if($post->getMedia('images')->count() > 0)
            <div class="card-footer">
                <div class="row">
                    <div class="col-12">
                        <b>
                            Bilder
                        </b>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                            @foreach($post->getMedia('images')->sortBy(['name']) as $media)
                                <a href="{{url('/image/'.$media->id)}}" target="_blank">
                                    <img class="mx-auto img-thumbnail" src="{{url('/image/'.$media->id)}}">
                                </a>
                            @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

