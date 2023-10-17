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
                                <div class="table-responsive-md">
                                    <table class="table" id="{{$day}}_themes">
                                        <thead class="thead-light">
                                        <tr>
                                            <th>Von</th>
                                            <th>Thema</th>
                                            <th class="d-none d-md-table-cell">Typ</th>
                                            <th style="max-width: 30%;"  class="d-none d-md-table-cell">Ziel</th>
                                            @if($group->hasAllocations)
                                                <th>
                                                    zugewiesen
                                                </th>
                                            @endif
                                            <th class="d-none d-md-table-cell">Dauer</th>
                                            <th class="d-none d-md-table-cell">Priorität</th>
                                            <th >Informationen</th>
                                        </tr>
                                        </thead>
                                        <tbody class="connectedSortable" >
                                        @foreach($dayThemes->sortByDesc('priority') as $theme)
                                            <tr id="{{$theme->id}}" class="@if($theme->protocols->where('created_at', '>', \Carbon\Carbon::now()->startOfDay())->count() > 0 ) bg-gradient-striped-success @endif     @if($theme->zugewiesen_an?->id === auth()->id()) border-left-10 @endif" data-priority="{{$theme->priority}}">
                                                <td class="align-content-center">
                                                    @if($theme->ersteller->getMedia('profile')->count() != 0)<img src="{{$theme->ersteller->photo()}}" class="avatar-xs" style="max-height: 30px; max-width: 30px;">@endif <div class="@if($theme->ersteller->getMedia('profile')->count() > 0) d-none @else d-inline  @endif">{{$theme->ersteller->name}}</div>
                                                </td>
                                                <td>
                                                    {{$theme->theme}}
                                                </td>

                                                <td  class="d-none d-md-table-cell">
                                                    {{$theme->type->type}}
                                                </td>
                                                <td class="d-none d-md-table-cell">
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
                                                <td class="d-none d-md-table-cell">
                                                    {{$theme->duration}} Minuten
                                                </td>
                                                <td id="priority_{{$theme->id}}" class="d-none d-md-table-cell">
                                                    @if ($theme->priorities->where('creator_id', auth()->id())->first())
                                                        <div class="progress">
                                                            <div class="progress-bar amount" role="progressbar" id="progress_{{$theme->id}}" style="width: {{100-$theme->priority}}%;" ></div>
                                                        </div>
                                                    @else
                                                        <input type="range" class="custom-range" id="theme_{{$theme->id}}" min="1" max="100" value="0" data-theme = "{{$theme->id}}" data-date="{{\Carbon\Carbon::createFromFormat('d.m.Y', $day)->format('Ymd')}}">
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="container-fluid">
                                                        <div class="row">
                                                            <div class="col-auto">
                                                                <a href="{{url(request()->segment(1)."/themes/$theme->id")}}">
                                                                    <i class="far fa-eye"></i> zeigen
                                                                </a>
                                                            </div>
                                                            <div class="col-auto d-none d-md-inline">
                                                                <a href="{{url(request()->segment(1)."/protocols/$theme->id")}}" >
                                                                    <i class="far fa-sticky-note"></i> Protokoll
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
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
                        let percent = 100 -responseText['priority']
                        let element = document.getElementById('priority_'+theme)

                        element.innerHTML = '<div class="progress">'+
                            '<div class="progress-bar amount" role="progressbar" id="progress_'+theme+'" style="width: '+percent+'%;" ></div>'+
                        '</div>'

                        document.getElementById(theme).dataset.priority = responseText['priority']
                        sortTable(responseText['day']+"_themes")
                        document.getElementById(theme).scrollTo()
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

        function sortTable(id ,) {
            var table, rows, switching, i, x, y, shouldSwitch;
            table = document.getElementById(id);
            switching = true;
            /* Make a loop that will continue until
            no switching has been done: */
            while (switching) {
                // Start by saying: no switching is done:
                switching = false;
                rows = table.rows;
                /* Loop through all table rows (except the
                first, which contains table headers): */
                for (i = 0; i < (rows.length - 1); i++) {
                    // Start by saying there should be no switching:
                    shouldSwitch = false;
                    /* Get the two elements you want to compare,
                    one from current row and one from the next: */
                    x = rows[i];
                    y = rows[i + 1];
                    // Check if the two rows should switch place:
                    if (((x.dataset.priority !== "") ? x.dataset.priority : 0) < ((y.dataset.priority !== "") ? y.dataset.priority : 0)) {
                        // If so, mark as a switch and break the loop:
                        console.log(x.dataset.priority + '<' + y.dataset.priority)
                        shouldSwitch = true;
                        break;
                    }
                }
                if (shouldSwitch) {
                    /* If a switch has been marked, make the switch
                    and mark that a switch has been done: */
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                }
            }

        }


    </script>
@endpush

@push('css')

@endpush
