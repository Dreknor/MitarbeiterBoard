@extends('layouts.layout')

@section('content')
    <div class="card-deck">
        @foreach($groups as $group)
            @if($loop->index%2 == 0)
                <div class="w-100 d-none d-sm-block d-md-none"><!-- wrap every 2 on sm--></div>
            @elseif($loop->index%3 == 0)
                <div class="w-100 d-none  d-md-block d-lg-block"><!-- wrap every 3 on md--></div>
            @endif
            <div class="card mt-2 "  style="background-color: {{$colors[$loop->index]}}">
                <div class="card-header">
                    <h5>
                        {{$group->name}}
                    </h5>
                </div>
                <div class="card-body">
                    <p>
                        <b>
                            nächste Besprechung:
                        </b>
                        {{optional(optional($group->themes->sortBy('date')->filter(function ($theme){
                           return $theme->completed == 0 and $theme->date->startOfDay()->greaterThanOrEqualTo(\Carbon\Carbon::now()->startOfDay());
                        })->first())->date)->format('d.m.Y')}}
                    </p>
                </div>
                <div class="card-body h-100">
                    <p>
                        <b>
                            Themen:
                        </b>
                    </p>
                    <ul class="list-group">
                        @foreach($group->themes->sortBy('date')->filter(function ($theme){
                               return $theme->completed == 0 and $theme->date->startOfDay()->greaterThanOrEqualTo(\Carbon\Carbon::now()->startOfDay());
                            }) as $theme)
                            <li class="list-group-item ">
                                {{$theme->theme}}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endforeach
    </div>
@endsection
