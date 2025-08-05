<tr class="@if($theme->protocols->where('created_at', '>', \Carbon\Carbon::now()->startOfDay())->count() > 0 ) bg-gradient-directional-teal text-white @endif">
    <td>
        @if($theme->ersteller->getMedia('profile')->count() != 0)
            <img src="{{$theme->ersteller->photo()}}" class="avatar-xs" title="{{$theme->ersteller->name}}">
        @endif
        <div class="@if($theme->ersteller->getMedia('profile')->count() > 0) d-none @else d-inline  @endif">
            {{$theme->ersteller->name}}
        </div>
    </td>
    <th class="w-50">
        {{ $theme->theme }}
    </th>
    <td class="w-25" id="priority_{{$theme->id}}">
        @if ($theme->priorities->where('creator_id', auth()->id())->first())
            <div class="progress">
                <div class="progress-bar amount" role="progressbar" id="progress_{{$theme->id}}" style="width: {{100-$theme->priority}}%;" ></div>
            </div>
        @else
            <input type="range" class="custom-range" id="theme_{{$theme->id}}" min="1" max="100" value="0" data-theme = "{{$theme->id}}" data-creatorid = "{{auth()->id()}}">
        @endif
    </td>
    <td>
        <a href="{{url(request()->segment(1)."/themes/$theme->id")}}" class="btn btn-light btn-sm float-right">
            <i class="far fa-eye"></i> zeigen
        </a>
    </td>
</tr>

