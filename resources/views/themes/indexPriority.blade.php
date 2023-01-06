@extends('layouts.app')


@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                @include('themes.element.header')
            </div>
            @can('create themes')
                <div class="card-body">
                    <a href="{{url(request()->segment(1).'/themes/create')}}" class="btn btn-block btn-primary">neues Thema</a>
                </div>
            @endcan
        </div>

        @if (count($themes) == 0)
            <div class="card">
                <div class="card-body">
                    <p>
                        Es gibt keine offenen Themen
                    </p>
                </div>
            </div>
        @else

            <div class="card" >
                <div class="card-body">
                    <div class="table-responsive-sm table-responsive-md">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Von</th>
                                <th>Thema</th>
                                <th>Datum</th>
                                <th>Typ</th>
                                @if($group->hasAllocations)
                                    <th>
                                        zugewiesen
                                    </th>
                                @endif
                                <th style="max-width: 30%;">Ziel</th>
                                <th>Priorität</th>
                                <th colspan="2">Informationen</th>
                            </tr>
                            </thead>
                            <tbody class="connectedSortable" >
                            @foreach($themes as $theme)
                                <tr id="{{$theme->id}}" @if($theme->protocols->where('created_at', '>', \Carbon\Carbon::now()->startOfDay())->count() > 0 ) class="bg-gradient-striped-success" @endif>
                                    <td>
                                        {{$theme->ersteller->name}}
                                    </td>
                                    <td>
                                        {{$theme->theme}}
                                    </td>

                                    <td>
                                        {{$theme->date->format('d.m.Y')}}
                                    </td>

                                    <td>
                                        {{$theme->type->type}}
                                    </td>
                                    @if($group->hasAllocations)
                                        <td>
                                            @if($theme->zugewiesen_an != null)
                                                <div class="badge bg-gradient-directional-amber p-2">
                                                    {{$theme->zugewiesen_an?->name}}
                                                </div>
                                            @endif
                                        </td>
                                    @endif
                                    <td>
                                        {{$theme->goal}}
                                    </td>
                                    <td id="priority_{{$theme->id}}">
                                        @if ($theme->priorities->where('creator_id', auth()->id())->first())
                                            <div class="progress">
                                                <div class="progress-bar amount" role="progressbar" style="width: {{100-$theme->priority}}%;" ></div>
                                            </div>
                                        @else
                                            <input type="range" class="custom-range" id="theme_{{$theme->id}}" min="1" max="100" value="0" data-theme = "{{$theme->id}}">
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{url(request()->segment(1)."/themes/$theme->id")}}">
                                            <i class="far fa-eye"></i> zeigen
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{url(request()->segment(1)."/protocols/$theme->id")}}">
                                            <i class="far fa-sticky-note"></i> Protokoll
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

@stop

@push('js')
    <script>
        $('input[type=range]').on("change", function() {
            let theme = $(this).data('theme');
            let url = "{{url(request()->segment(1).'/themes')}}";
            $.ajax({
                    type: "POST",
                    url: '{{url('priorities')}}',
                    data: {
                        "priority": $(this).val(),
                        'theme': theme,
                        "_token": "{{ csrf_token() }}",
                    },
                success: function(responseText){
                    window.location.replace(url);
                }
            });
        });


    </script>
@endpush

@push('css')

@endpush
