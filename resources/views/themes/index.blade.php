@extends('layouts.app')


@section('content')
    <div class="container-fluid">
        <div class="sticky-top">
            <div class="card ">
                <div class="card-header">
                    @include('themes.element.header')
                </div>
                @can('create themes')
                    <div class="card-body">
                        <a href="{{url(request()->segment(1).'/themes/create')}}" class="btn btn-block btn-bg-gradient-x-blue-cyan">neues Thema</a>
                    </div>
                @endcan
            </div>
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
                        <div class="card" id="{{\Carbon\Carbon::createFromFormat('d.m.Y', $day)->format('Ymd')}}" >
                            <div class="card-header bg-gradient-directional-blue-grey text-white">
                                <div class="row">
                                    <div class="col-sm-12 col-md-8">
                                        <h5 class="card-title">
                                            {{$day}}
                                        </h5>

                                        <p class="small">
                                            Dauer: {{$dayThemes->sum('duration')}} Minuten
                                        </p>
                                    </div>
                                    <div class="col-sm-12 col-md-4 pull-right">
                                        @can('move themes')
                                            <div class="pull-right">
                                                <a href="#" title="Alle Themen verschieben" class="changeDateLink" id="link_{{\Carbon\Carbon::createFromFormat('d.m.Y', $day)->format('Ymd')}}" data-date="{{\Carbon\Carbon::createFromFormat('d.m.Y', $day)->format('Ymd')}}">
                                                    <i class="fa fa-calendar-day"></i>
                                                </a>
                                                <div class="d-none" id="form_{{\Carbon\Carbon::createFromFormat('d.m.Y', $day)->format('Ymd')}}">
                                                    <form method="post" action="{{url(request()->segment(1).'/move/themes')}}" class="form-inline" >
                                                        @csrf
                                                        <input type="date" class="form-control" name="date" value="{{\Carbon\Carbon::createFromFormat('d.m.Y', $day)->addWeek()->format('Y-m-d')}}">
                                                        <input type="hidden" class="form-control" name="oldDate" value="{{\Carbon\Carbon::createFromFormat('d.m.Y', $day)->format('Y-m-d')}}">
                                                        <button type="submit" class="btn btn-sm btn-success">verschieben</button>
                                                    </form>
                                                </div>
                                            </div>
                                        @endcan
                                    </div>
                                </div>


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
                                            @if($group->hasAllocations)
                                                <th>
                                                    zugewiesen
                                                </th>
                                            @endif
                                            <th>Dauer</th>
                                            <th>Priorität</th>
                                            <th colspan="2">Informationen</th>
                                        </tr>
                                        </thead>
                                        <tbody class="connectedSortable" >
                                        @foreach($dayThemes->sortByDesc('priority') as $theme)
                                            <tr id="{{$theme->id}}" @if($theme->protocols->where('created_at', '>', \Carbon\Carbon::now()->startOfDay())->count() > 0 ) class="bg-gradient-striped-success" @endif>
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
                                                    {{$theme->duration}} Minuten
                                                </td>
                                                <td id="priority_{{$theme->id}}">
                                                    @if ($theme->priorities->where('creator_id', auth()->id())->first())
                                                        <div class="progress">
                                                            <div class="progress-bar amount" role="progressbar" style="width: {{100-$theme->priority}}%;" ></div>
                                                        </div>
                                                    @else
                                                        <input type="range" class="custom-range" id="theme_{{$theme->id}}" min="1" max="100" value="0" data-theme = "{{$theme->id}}" data-date="{{\Carbon\Carbon::createFromFormat('d.m.Y', $day)->format('Ymd')}}">
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
                    @endforeach
        @endif
    </div>

@stop

@push('js')
    <script>
        $('input[type=range]').on("change", function() {
            let theme = $(this).data('theme');
            let url = "{{url(request()->segment(1).'/themes/' )}}"
            console.log(url)
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

        $('.changeDateLink').on('click', function (link){
            let date;
            date = $(this).data('date');
            id  = '#form_' + date;

            const form = $(id);

            if ($(form).hasClass('d-none')){
                $(form).removeClass('d-none');
                this.text = 'ausblenden'
            } else {
                $(form).addClass('d-none');
                this.innerHTML = '<i class="fa fa-calendar-day"></i>';
            }


        });

    </script>
@endpush

@push('css')

@endpush
