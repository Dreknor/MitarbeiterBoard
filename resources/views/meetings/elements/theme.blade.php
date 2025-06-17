<li class="list-group-item">
    <div class="row">
        <div class="col-auto align-content-center">
            @if($theme->ersteller->getMedia('profile')->count() != 0)
                <img src="{{$theme->ersteller->photo()}}" class="avatar-xs" title="{{$theme->ersteller->name}}">
            @endif
                <div class="@if($theme->ersteller->getMedia('profile')->count() > 0) d-none @else d-inline  @endif">
                    {{$theme->ersteller->name}}
                </div>
        </div>
        <div class="col-10">
            <div class="row">
                <div class="col-md-5 col-sm-10">
                    <b>{{ $theme->theme }}</b>
                </div>
                <div id="priority_{{$theme->id}}" class="col-md-5 col-sm-10">
                    @if ($theme->priorities->where('creator_id', auth()->id())->first())
                        <div class="progress">
                            <div class="progress-bar amount" role="progressbar" id="progress_{{$theme->id}}" style="width: {{100-$theme->priority}}%;" ></div>
                        </div>
                    @else
                        <input type="range" class="custom-range" id="theme_{{$theme->id}}" min="1" max="100" value="0" data-theme = "{{$theme->id}}" data-creatorid = "{{auth()->id()}}">
                    @endif
                </div>
                <div class="col-md-2 col-sm-2">
                    <a href="{{url(request()->segment(1)."/themes/$theme->id")}}" class="btn btn-primary btn-sm float-right">
                        <i class="far fa-eye"></i> zeigen
                    </a>
            </div>
        </div>

    </div>
</li>
