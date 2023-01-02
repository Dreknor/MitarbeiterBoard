@extends('layouts.app')

@section('title')
    Dienstplan bearbeiten
@endsection

@section('site-title')
    @if(!$roster->is_template)
        Dienstplan bearbeiten
    @else
        Vorlage bearbeiten
    @endif
@endsection


@section('content')
    <div class="container-fluid">
        @include('personal.rosters.elements.info')
        <div class=" sticky-top">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <a href="#{{$roster->start_date->format('Y-m-d')}}" class="btn btn-sm btn-block btn-outline-primary">Montag</a>
                        </div>
                        <div class="col">
                            <a href="#{{$roster->start_date->addDays(1)->format('Y-m-d')}}" class="btn btn-sm  btn-block btn-outline-primary">Dienstag</a>
                        </div>
                        <div class="col">
                            <a href="#{{$roster->start_date->addDays(2)->format('Y-m-d')}}" class="btn btn-sm btn-block  btn-outline-primary">Mittwoch</a>
                        </div>
                        <div class="col">
                            <a href="#{{$roster->start_date->addDays(3)->format('Y-m-d')}}" class="btn btn-sm btn-block  btn-outline-primary">Donnerstag</a>
                        </div>
                        <div class="col">
                            <a href="#{{$roster->start_date->addDays(4)->format('Y-m-d')}}" class="btn btn-sm btn-block  btn-outline-primary">Freitag</a>
                        </div>
                        <div class="col">
                            <a href="#{{$roster->start_date->addDays(5)->format('Y-m-d')}}" class="btn btn-sm btn-block  btn-outline-primary">Wochenende</a>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        @for($day = $roster->start_date->copy(); $day->lessThanOrEqualTo($roster->start_date->endOfWeek()); $day->addDay())
            @cache('roster_'.$roster->id.'_'.$day->format('Ymd'))
                <div id="{{$day->format('Y-m-d')}}">

                </div>

                <div class="card @if($roster->is_template) bg-info bg-accent-2 @endif">
                    <div class="card-header">
                        <div @class(['card-title'])>
                            <div class="d-flex w-100 justify-content-between">
                                {{$day->locale('de')->dayName}}
                                @if(!$roster->is_template), den {{$day->format('d.m.Y')}}@endif
                                <small>
                                    <a @class(['trashDay', 'm-2', 'text-danger']) data-day="{{$day->format('Y-m-d')}}" href="#">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                    <a href="{{route('toggleDayView', $day->format('Y-m-d'))}}" class="m-2">
                                        @if(session()->exists($day->format('Y-m-d')))
                                            <i class="fa fa-expand-arrows-alt"></i>
                                        @else
                                            <i class="fa fa-compress-arrows-alt"></i>
                                        @endif
                                    </a>
                                </small>
                            </div>
                        </div>
                        <p class='description'>
                            {{is_holiday($day)?->title}}
                        </p>
                    </div>
                    <div
                        @class(["card-body", 'd-none' => session($day->format('Y-m-d')) == true]) id="dayRoster_{{$day->format('Y-m-d')}}">
                        <div class="card-group ">
                            @include('personal.rosters.elements.time')
                            @foreach($employes as $employe)
                                <div class="card border @if(!$loop->first) border-left-0 @endif">
                                    <div class="card-header border-bottom" style="height: 45px;">
                                        {{$employe->vorname}}
                                        @if($working_times->searchWorkingTime($employe, $day)->first()?->needs_break($events))
                                            <div @class(['description', 'd-inline', 'pull-right', 'text-danger'])>
                                                <small>Pause fehlt</small>
                                            </div>
                                        @endif
                                    </div>
                                    <div
                                        @class(['card-body','border-bottom', 'pt-0', 'pb-0', 'info' => $working_times->searchWorkingTime($employe, $day)->first()?->needs_break($events)]) style="max-height: 50px; min-height: 50px;">
                                        <div @class(['row', 'h-100'])>
                                            <div class="col m-0 p-1 workingTime "
                                                 data-date="{{$day->format('Y-m-d')}}"
                                                 @if($working_times->searchWorkingTime($employe, $day)->count() == 1)
                                                 data-start="{{optional($working_times->searchWorkingTime($employe, $day)->first()->start)->format('H:i')}}"
                                                 data-end="{{optional($working_times->searchWorkingTime($employe, $day)->first()->end)->format('H:i')}}"
                                                 data-function="{{optional($working_times->searchWorkingTime($employe, $day)->first())->function}}"
                                                 @endif
                                                 data-employe="{{$employe->id}}">
                                                @if($working_times->searchWorkingTime($employe, $day)->count() == 1)
                                                    {{optional($working_times->searchWorkingTime($employe, $day)->first()->start)->format('H:i')}}
                                                @else
                                                    &nbsp;
                                                @endif
                                            </div>
                                            <div
                                                @class(['col','m-0','p-1','workingTime'])data-date="{{$day->format('Y-m-d')}}"
                                                @if($working_times->searchWorkingTime($employe, $day)->count() == 1)
                                                data-start="{{optional($working_times->searchWorkingTime($employe, $day)->first()->start)->format('H:i')}}"
                                                data-end="{{optional($working_times->searchWorkingTime($employe, $day)->first()->end)->format('H:i')}}"
                                                data-function="{{optional($working_times->searchWorkingTime($employe, $day)->first())->function}}"
                                                @endif
                                                data-employe="{{$employe->id}}">
                                                @if($working_times->searchWorkingTime($employe, $day)->count() == 1)
                                                    {{optional($working_times->searchWorkingTime($employe, $day)->first()->end)->format('H:i')}}
                                                @else
                                                    &nbsp;
                                                @endif

                                            </div>
                                        </div>

                                    </div>
                                    <div @class(['card-body' ,'p-0', 'm-0']) style="height: 534px;">
                                        <ul @class(['selectable']) data-employe="{{$employe->id}}"
                                            data-date="{{$day->format('Y-m-d')}}">
                                            @for($time=\Carbon\Carbon::parse($day->copy()->format('d.m.Y 8:00')); $time->format('H:i') < '14:30'; $time->addMinutes(15))
                                                @if($events->searchRosterEvent($employe, $time)->count() > 0 and $events->searchRosterEvent($employe, $time)->first()->start == $time)
                                                    <li @class(['Termin'])
                                                        draggable="true" ondragstart="drag(event)"
                                                        id="task_{{$events->searchRosterEvent($employe, $time)->first()->id}}"
                                                        data-id="{{$events->searchRosterEvent($employe, $time)->first()->id}}"
                                                        data-start="{{$events->searchRosterEvent($employe, $time)->first()->start->format('H:i')}}"
                                                        data-end="{{$events->searchRosterEvent($employe, $time)->first()->end->format('H:i')}}"
                                                        data-date="{{$events->searchRosterEvent($employe, $time)->first()->date->format('Y-m-d')}}"
                                                        data-event="{{$events->searchRosterEvent($employe, $time)->first()->event}}"
                                                        data-employe="{{$events->searchRosterEvent($employe, $time)->first()->employe_id}}"
                                                        @if($events->searchRosterEvent($employe, $time)->first()->end->lessThanOrEqualTo(\Carbon\Carbon::createFromFormat('Y-m-d H:i', $day->format('Y-m-d').' 14:00')))
                                                            style="height: {{ ($events->searchRosterEvent($employe, $time)->first()->duration / 15) * 20 }}px"
                                                        @else
                                                            style="height: {{ ($events->searchRosterEvent($employe, $time)->first()->start->diffInMinutes(\Carbon\Carbon::createFromFormat('Y-m-d H:i', $time->format('Y-m-d'). ' 14:30')) / 15) * 20 }}px"
                                                            @endif
                                                        >
                                                            {{$events->searchRosterEvent($employe, $time)->first()->event}}
                                                            @if($events->searchRosterEvent($employe, $time)->first()->end->format('H:i') > '14:30')
                                                                (bis {{$events->searchRosterEvent($employe, $time)->first()->end->format('H:i')}}
                                                                Uhr)
                                                            @endif
                                                        </li>
                                                    @elseif(!$events->searchRosterEvent($employe, $time)->count() > 0)
                                                        <li @class('leererTermin leererTermin_'.$time->minute.' selectable')
                                                            id="date_{{$employe->id}}_{{$time->format('Y-m-d_H:i')}}"
                                                            data-time="{{$time->format('H:i')}}"
                                                            data-date="{{$time->format('Y-m-d')}}"
                                                            ondrop="drop(event)" ondragover="allowDrop(event)"
                                                            ondragleave="leveDrop(event)">

                                                        </li>

                                                @endif

                                            @endfor
                                        </ul>
                                    </div>
                                    <div
                                        @class(['card-footer','border-top', 'm-0', 'workingTime']) style="max-height: 60px; min-height: 60px;"
                                        data-date="{{$day->format('Y-m-d')}}"
                                        data-employe="{{$employe->id}}"
                                        @if($working_times->searchWorkingTime($employe, $day)->count() == 1)
                                        data-start="{{optional($working_times->searchWorkingTime($employe, $day)->first()->start)->format('H:i')}}"
                                        data-end="{{optional($working_times->searchWorkingTime($employe, $day)->first()->end)->format('H:i')}}"
                                        data-function="{{optional($working_times->searchWorkingTime($employe, $day)->first())->function}}"
                                        @endif
                                    >
                                        <div @class(['aufgabe'])
                                             id="{{$employe->id.'_'.$day->format('Y-m-d'.'_function')}}"
                                        >
                                            @if($working_times->searchWorkingTime($employe, $day)->count() == 1)
                                                {{$working_times->searchWorkingTime($employe, $day)->first()->function}}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                            @include('personal.rosters.elements.bookmarks')
                            @includeWhen($roster->department->roster_checks->count() > 0,'personal.rosters.elements.checks')

                        </div>
                    </div>
                </div>
            @endcache
        @endfor


        @include('personal.rosters.modals.taskModal')
        @include('personal.rosters.modals.editTaskModal')
        @include('personal.rosters.modals.workTimeModal')
        @include('personal.rosters.modals.trashDayModal')

@endsection

@push('js')
            <script type="text/javascript" src="{{asset('js/jquery-ui.js')}}"></script>
            <script type="text/javascript" src="{{asset('js/bootstrap-select.min.js')}}"></script>
            <script type="text/javascript" src="{{asset('js/functions.js')}}"></script>

            <!--
                Arbeitszeit
            -->
            <script type="text/javascript">
                $('.Termin').on('click', function () {
                    document.getElementById('editTaskForm').action = "{{url('tasks/')}}" + '/' + $(this).data('id'); //Will set it
                    document.getElementById('delteTaskForm').action = "{{url('tasks/')}}" + '/' + $(this).data('id'); //Will set it
                    document.getElementById('rememberEvent').href = "{{url('tasks/')}}" + '/' + $(this).data('id') + '/remember';  //Will set it

                    $('#editDate').val($(this).data('date'));
                    console.log($(this).data('date'))
                    console.log($(this))
                    $('#editEvent').val($(this).data('event'));
                    $('#editStart').val($(this).data('start'));
                    $('#editEnd').val($(this).data('end'));

                    $(":checkbox").prop('checked', false).parent().removeClass('active');

                    $('input[type="checkbox"][value="' + $(this).data('employe') + '"]').prop("checked", true).parent().addClass('active');


                        $('#editTaskModal').modal('show');
                    })

                $(function () {
                        $("ul.selectable").selectable({
                            cancel: 'li.Termin',
                            filter: "li:not(.Termin)",
                            start: function () {

                                $('.ui-selected').removeClass('ui-selected')
                            },
                            stop: function (){
                                var $selected = $(this).children('.ui-selected');
                                var date = $(this).data('date');
                                var employe = $(this).data('employe');
                                var $first = $selected.first();
                                var $last = $selected.last();

                                $first = $first[0];
                                $last = $last[0];

                                var start = $first.getAttribute('data-time');
                                var ende = $last.getAttribute('data-time');


                                $('#date').val(date);
                                $('#start').val(start);
                                $('#end').val(addMinutes(ende, 15));

                                $(":checkbox").prop('checked', false).parent().removeClass('active');

                                $('input[type="checkbox"][value="' + employe + '"]').prop("checked", true).parent().addClass('active');
                                $('#taskModal').modal('show');
                                $('#taskModal').on('shown.bs.modal', function () {
                                    $('#event').trigger('focus')
                                })
                            }
                        });
                    });

            </script>
            <script type="text/javascript">
                $('.workingTime').on('click', function (e) {

                    $('#WorkingTimeDate').prop('value', $(this).data('date'))
                    $('#working_time_employe_id').prop('value', $(this).data('employe'))


                    $('#working_time_start').prop('value', $(this).data('start'))
                    $('#working_time_end').prop('value', $(this).data('end'))

                    $('#working_time_function').prop('value', $(this).data('function'))

                    $('#workTimeModal').modal('show')
                })
            </script>
            <script type="text/javascript">
                function allowDrop(ev) {
                    ev.target.style.backgroundColor = "rgba(0,230,123,0.58)"
                    ev.preventDefault();

                }

                    function leveDrop(ev) {
                        ev.target.style.backgroundColor = ""
                        ev.preventDefault();

                    }

                    function drag(ev) {
                        ev.dataTransfer.setData("text", ev.target.id);
                    }

                    function drop(ev) {
                        ev.preventDefault();
                        var data = ev.dataTransfer.getData("text");
                        $.ajax({
                            type: 'POST',
                            url: '{{url('tasks/update')}}',
                            data: {
                                '_token': "<?php echo csrf_token() ?>",
                                '_method': "PATCH",
                                employe_id: ev.target.closest('ul').dataset.employe,
                                task: data,
                                start: ev.target.dataset.time,
                                date: ev.target.dataset.date,

                            },

                            success: function (result) {
                                location.reload();
                            },
                            error: function (result) {
                                location.reload();
                            }
                        });
                    }

            </script>

            <!--trashDay-->
            <script type="text/javascript">
                $('.trashDay').on('click', function (ev) {
                    ev.preventDefault();
                    $('#trashDate').prop('value', $(this).data('day'))

                    $('#trashDayModal').modal('show')

                })
            </script>

            <!-- add News -->
            <script type="text/javascript">
                $('#addNews').on('click', function (ev) {
                    ev.preventDefault();
                    $('#addNewsForm').toggleClass('d-none')

                    $(this).toggleClass('d-none')

                })
            </script>
        @endpush

@push('css')
                <link href="{{asset('css/bootstrap-select.css')}}" rel="stylesheet">
                 <link href="{{asset('css/style.css')}}" rel="stylesheet"/>

            <style>
                .selectable .ui-selecting {
                    background: rgba(20, 217, 243, 0.61);
                }

                .selectable .ui-selected {
                    background: rgba(20, 217, 243, 0.61);
                    color: white;
                }

                td.border-dashed {
                    border-bottom-width: 1px !important;
                    border-bottom-color: #00BCD4 !important;
                    border-bottom-style: dotted !important;
                }


            </style>

    @endpush
