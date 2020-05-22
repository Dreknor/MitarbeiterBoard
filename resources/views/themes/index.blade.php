@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Themen {{request()->segment(1)}}</h5>
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
                    @foreach($themes as $day => $dayThemes)
                        <div class="card" id="{{$day}}" >
                            <div class="card-header">
                                <h5 class="card-title">
                                    {{$day}}
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive-sm table-responsive-md">
                                    <table class="table">
                                        <thead>
                                        <tr>
                                            <th>Von</th>
                                            <th>Thema</th>
                                            <th>Typ</th>
                                            <th style="max-width: 30%;">Ziel</th>
                                            <th>Dauer</th>
                                            <th>Priorität</th>
                                            <th>Informationen</th>
                                        </tr>
                                        </thead>
                                        <tbody class="connectedSortable" >
                                        @foreach($dayThemes->sortByDesc('priority') as $theme)
                                            <tr id="{{$theme->id}}" @if($theme->protocols->where('created_at', '>', \Carbon\Carbon::now()->startOfDay())->count() > 0 ) class="bg-warning" @endif>
                                                <td>
                                                    {{$theme->ersteller->name}}
                                                </td>
                                                <td>
                                                    {{$theme->theme}}
                                                </td>

                                                <td>
                                                    {{$theme->type->type}}
                                                </td>
                                                <td>
                                                    {{$theme->goal}}
                                                </td>
                                                <td>
                                                    {{$theme->duration}} Minuten
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
                                                    <a href="{{url(request()->segment(1)."/themes/$theme->id")}}">anzeigen</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endforeach
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
