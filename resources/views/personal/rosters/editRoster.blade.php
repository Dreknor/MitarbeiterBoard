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
                        @php($navLabels=['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Wochenende'])
                        @foreach($navLabels as $idx => $label)
                            @if(isset($days[$idx]))
                                <div class="col">
                                    <a href="#{{$days[$idx]->format('Y-m-d')}}" class="btn btn-sm btn-block btn-outline-primary">{{$label}}</a>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @foreach($days as $day)
            @php($dayKey = $day->format('Y-m-d'))
            @cache('roster_'.$roster->id.'_'.$day->format('Ymd'))
                <div id="{{$dayKey}}"></div>
                <div class="card @if($roster->is_template) bg-info bg-accent-2 @endif">
                    <div class="card-header">
                        <div @class(['card-title'])>
                            <div class="d-flex w-100 justify-content-between">
                                {{$day->locale('de')->dayName}}
                                @if(!$roster->is_template), den {{$day->format('d.m.Y')}}@endif
                                <small>
                                    <a @class(['trashDay', 'm-2', 'text-danger']) data-day="{{$dayKey}}" href="#">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                    <a href="{{route('toggleDayView', [$roster->id,$dayKey])}}" class="m-2">
                                        @if(session()->exists($dayKey))
                                            <i class="fa fa-expand-arrows-alt"></i>
                                        @else
                                            <i class="fa fa-compress-arrows-alt"></i>
                                        @endif
                                    </a>
                                </small>
                            </div>
                        </div>
                        <p class='description'>
                            {{is_holiday($day)? 'Feiertag' : ''}}
                        </p>
                    </div>
                    <div @class(["card-body", 'd-none' => session($dayKey) == true]) id="dayRoster_{{$dayKey}}">
                        <div class="card-group ">
                            @include('personal.rosters.elements.time')
                            @foreach($employes as $employe)
                                @php($wt = $wtIndex[$employe->id][$dayKey] ?? null)
                                @php($empDayEvents = ($events->where('employe_id',$employe->id)->filter(fn($e)=>$e->date->format('Y-m-d')===$dayKey)))
                                <div class="card border roster-col @if(!$loop->first) border-left-0 @endif">
                                    <div class="card-header border-bottom" style="height:45px;">
                                        @if($employe->geburtstag?->isBirthday($day)) <i class="fa-solid fa-cake-candles"></i> @endif
                                            {{$employe->vorname}}
                                        @if($employe->geburtstag?->isBirthday($day)) <i class="fa-solid fa-cake-candles"></i> @endif
                                        @if($wt?->needs_break($events))
                                            <div @class(['description','d-inline','pull-right','text-danger'])><small>Pause fehlt</small></div>
                                        @endif
                                        @php($empEvents = $events->where('employe_id',$employe->id))
                                        @if($wt?->diff_start_first_event($empEvents))
                                            <div @class(['description','d-inline','pull-right','text-danger'])><small>Arbeitszeit falsch</small></div>
                                        @endif
                                    </div>
                                    <div @class(['card-body','border-bottom','pt-0','pb-0','info'=>$wt?->needs_break($events)]) style="max-height:50px;min-height:50px;">
                                        <div @class(['row','h-100'])>
                                            <div class="col m-0 p-1 workingTime" data-date="{{$dayKey}}" data-employe="{{$employe->id}}" @if($wt) data-start="{{$wt->start?->format('H:i')}}" data-end="{{$wt->end?->format('H:i')}}" data-function="{{$wt->function}}" @endif>
                                                {{$wt?->start?->format('H:i') ?? ' '}}
                                            </div>
                                            <div class="col m-0 p-1 workingTime" data-date="{{$dayKey}}" data-employe="{{$employe->id}}" @if($wt) data-start="{{$wt->start?->format('H:i')}}" data-end="{{$wt->end?->format('H:i')}}" data-function="{{$wt->function}}" @endif>
                                                {{$wt?->end?->format('H:i') ?? ' '}}
                                            </div>
                                        </div>
                                    </div>
                                    <div @class(['card-body','p-0','m-0']) style="height:534px;">
                                        <div class="timeline" data-employe="{{$employe->id}}" data-date="{{$dayKey}}">
                                            @php($dayStart='08:00')
                                            @php($dayEnd='14:30')
                                            @php($pxPerMinute = 20/15)
                                            {{-- Stundenlinien --}}
                                            @for($h=8;$h<=14;$h++)
                                                @php($offset = (($h*60)-480)*$pxPerMinute)
                                                <div class="timeline-hour-line" style="top:{{$offset}}px;">
                                                    <span class="timeline-hour-label">{{sprintf('%02d:00',$h)}}</span>
                                                </div>
                                            @endfor
                                            {{-- Halb-Stunde 14:30 Markierung --}}
                                            @php($halfOffset = ((14*60+30)-480)*$pxPerMinute)
                                            <div class="timeline-half-hour-line" style="top:{{$halfOffset}}px;"><span class="timeline-hour-label">14:30</span></div>
                                            @foreach($empDayEvents as $ev)
                                                @php($start = max($ev->start->format('H:i'), $dayStart))
                                                @php($endDisplay = min($ev->end->format('H:i'), $dayEnd))
                                                @php($startMinutes = (int)substr($start,0,2)*60 + (int)substr($start,3,2))
                                                @php($endMinutes = (int)substr($endDisplay,0,2)*60 + (int)substr($endDisplay,3,2))
                                                @php($top = ($startMinutes - 480) * $pxPerMinute)
                                                @php($height = max(4, ($endMinutes - $startMinutes) * $pxPerMinute))
                                                <div class="roster-event Termin"
                                                     draggable="true"
                                                     id="task_{{$ev->id}}"
                                                     data-id="{{$ev->id}}"
                                                     data-start="{{$ev->start->format('H:i')}}"
                                                     data-end="{{$ev->end->format('H:i')}}"
                                                     data-original-start="{{$ev->start->format('H:i')}}"
                                                     data-original-end="{{$ev->end->format('H:i')}}"
                                                     data-date="{{$dayKey}}"
                                                     data-event="{{$ev->event}}"
                                                     data-employe="{{$ev->employe_id}}"
                                                     style="top:{{$top}}px; height:{{$height}}px;">
                                                    <span class="ev-label">{{$ev->event}}</span>
                                                    @if($ev->end->format('H:i') > $dayEnd)
                                                        <small class="text-muted">(bis {{$ev->end->format('H:i')}})</small>
                                                    @endif
                                                </div>
                                            @endforeach
                                            <div class="selection-overlay d-none"></div>
                                            <div class="time-indicator d-none"><span class="ti-label"></span></div>
                                        </div>
                                    </div>
                                    <div @class(['card-footer','border-top','m-0','workingTime']) style="max-height:60px;min-height:60px;" data-date="{{$dayKey}}" data-employe="{{$employe->id}}" @if($wt) data-start="{{$wt->start?->format('H:i')}}" data-end="{{$wt->end?->format('H:i')}}" data-function="{{$wt->function}}" @endif>
                                        <div @class(['aufgabe']) id="{{$employe->id.'_'.$dayKey.'_function'}}">{{$wt?->function}}</div>
                                    </div>
                                </div>
                            @endforeach

                            @includeWhen($events->where('employe_id', null)->where('date', $day)->count() > 0,'personal.rosters.elements.bookmarks')
                            @includeWhen($roster->department->roster_checks->count() > 0,'personal.rosters.elements.checks')

                        </div>
                    </div>
                </div>
            @endcache
        @endforeach

        @include('personal.rosters.modals.taskModal')
        @include('personal.rosters.modals.editTaskModal')
        @include('personal.rosters.modals.workTimeModal')
        @include('personal.rosters.modals.trashDayModal')

@endsection

@push('js')
    <script type="text/javascript" src="{{asset('js/bootstrap-select.min.js')}}"></script>
    <script type="text/javascript" src="{{asset('js/functions.js')}}"></script>
    <script type="text/javascript">
        $('.Termin').on('click', function () {
            document.getElementById('editTaskForm').action = "{{url('tasks/')}}" + '/' + $(this).data('id');
            document.getElementById('delteTaskForm').action = "{{url('tasks/')}}" + '/' + $(this).data('id');
            document.getElementById('rememberEvent').href = "{{url('tasks/')}}" + '/' + $(this).data('id') + '/remember';
            $('#editDate').val($(this).data('date'));
            $('#editEvent').val($(this).data('event'));
            $('#editStart').val($(this).data('start'));
            $('#editEnd').val($(this).data('end'));
            $(":checkbox").prop('checked', false).parent().removeClass('active');
            $('input[type="checkbox"][value="' + $(this).data('employe') + '"]').prop("checked", true).parent().addClass('active');
            $('#editTaskModal').modal('show');
        })
        $('.workingTime').on('click', function () {
            $('#WorkingTimeDate').val($(this).data('date'))
            $('#working_time_employe_id').val($(this).data('employe'))
            $('#working_time_start').val($(this).data('start'))
            $('#working_time_end').val($(this).data('end'))
            $('#working_time_function').val($(this).data('function'))
            $('#workTimeModal').modal('show')
        })
        $('.trashDay').on('click', function (ev) { ev.preventDefault(); $('#trashDate').val($(this).data('day')); $('#trashDayModal').modal('show') })
        $('#addNews').on('click', function (ev) { ev.preventDefault(); $('#addNewsForm').toggleClass('d-none'); $(this).toggleClass('d-none') })
    </script>
@endpush

@push('css')
    <link href="{{asset('css/bootstrap-select.css')}}" rel="stylesheet">
    <link href="{{asset('css/style.css')}}" rel="stylesheet"/>
    <style>
        :root { --px-per-minute: 1.3333333333; }
        .timeline{position:relative;height:520px;background:repeating-linear-gradient(to bottom,#fafafa 0,#fafafa 14px,#f0f0f0 14px,#f0f0f0 28px);border-left:1px solid #ccc;overflow:hidden;}
        .timeline-hour-line,.timeline-half-hour-line{position:absolute;left:0;right:0;height:1px;background:#bdbdbd;z-index:5;}
        .timeline-half-hour-line{background:#d2d2d2;border-top:1px dashed #c5c5c5;}
        .timeline-hour-label{position:absolute;left:2px;top:-7px;font-size:9px;color:#555;background:#fff;padding:0 2px;}
        .roster-event{position:absolute;left:4px;right:4px;background:#2196f3;color:#fff;font-size:11px;line-height:1.1;padding:2px 4px;border-radius:3px;cursor:move;overflow:hidden;z-index:20;transition:box-shadow .15s, background .15s;}
        .roster-event.dragging{opacity:.85;box-shadow:0 0 0 2px rgba(255,255,255,0.4);}
        .roster-event.conflict{outline:2px solid #ff5722;background:#ff7043;}
        .roster-event.conflict-preview{outline:2px solid #ff9800;background:#ffa726;}
        .selection-overlay{position:absolute;left:0;right:0;background:rgba(0,188,212,0.25);border:1px solid rgba(0,188,212,0.6);pointer-events:none;z-index:40;transition:background .15s,border-color .15s;}
        .selection-overlay.conflict{background:rgba(244,67,54,0.25);border-color:#f44336;}
        .time-indicator{position:absolute;left:0;right:0;height:1px;background:#ff5722;z-index:35;}
        .time-indicator .ti-label{position:absolute;right:2px;top:-7px;font-size:10px;background:#ff5722;color:#fff;padding:0 3px;border-radius:2px;}
        .drag-target{outline:2px dashed #00bcd4;}
    </style>
@endpush

@push('js')
    <script>
        (function(){
            const PX_PER_MIN = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--px-per-minute')) || (20/15);
            const DAY_START_MIN = 8*60; const DAY_END_MIN = 14*60+30;
            function snap(min){return Math.floor(min/15)*15;}
            function minutesToTime(m){const h=(m/60|0).toString().padStart(2,'0');const mi=(m%60).toString().padStart(2,'0');return h+':'+mi;}
            function timeStrToMin(t){return parseInt(t.substring(0,2))*60+parseInt(t.substring(3,5));}
            function topFromTimeStr(t){return (timeStrToMin(t)-DAY_START_MIN)*PX_PER_MIN;}
            function heightFromTimes(start,end){return (timeStrToMin(end)-timeStrToMin(start))*PX_PER_MIN;}
            function hasConflict(tl,startMin,endMin,ignoreId){
                const rangeOk = (aS,aE,bS,bE)=> !(aE<=bS || aS>=bE);
                const evs = tl.querySelectorAll('.roster-event');
                for(const ev of evs){
                    if(ev.id===ignoreId) continue;
                    const s=timeStrToMin(ev.dataset.start); const e=timeStrToMin(ev.dataset.end);
                    if(rangeOk(startMin,endMin,s,e)) return true;
                }
                return false;
            }
            // Modal Öffnen
            $(document).on('click','.roster-event',function(){ const el=$(this); $('#editTaskForm').attr('action',"{{url('tasks/')}}/"+el.data('id')); $('#delteTaskForm').attr('action',"{{url('tasks/')}}/"+el.data('id')); $('#rememberEvent').attr('href',"{{url('tasks/')}}/"+el.data('id')+'/remember'); $('#editDate').val(el.data('date')); $('#editEvent').val(el.data('event')); $('#editStart').val(el.data('start')); $('#editEnd').val(el.data('end')); $(":checkbox").prop('checked',false).parent().removeClass('active'); $('input[type="checkbox"][value="'+el.data('employe')+'"]').prop('checked',true).parent().addClass('active'); $('#editTaskModal').modal('show'); });
            // Auswahl neuer Event + Hover Linie
            document.querySelectorAll('.timeline').forEach(tl=>{ let startY=null, sel=tl.querySelector('.selection-overlay'); const indicator=tl.querySelector('.time-indicator'); const label=indicator.querySelector('.ti-label'); let lastY=0; function updateIndicator(y){lastY=y; let min=snap(Math.round(y/PX_PER_MIN)+DAY_START_MIN); if(min<DAY_START_MIN)min=DAY_START_MIN; if(min>DAY_END_MIN)min=DAY_END_MIN; indicator.style.top=(min-DAY_START_MIN)*PX_PER_MIN+'px'; label.textContent=minutesToTime(min);} tl.addEventListener('mouseenter',()=>indicator.classList.remove('d-none')); tl.addEventListener('mouseleave',()=>{indicator.classList.add('d-none'); if(startY!==null) finishSelection({offsetY:lastY});}); tl.addEventListener('mousemove',e=>{updateIndicator(e.offsetY); if(startY!==null){const cur=e.offsetY; const top=Math.min(startY,cur); const h=Math.abs(cur-startY); sel.style.top=top+'px'; sel.style.height=Math.max(2,h)+'px'; // Vorschau-Konflikt
                        const startMin=snap(Math.round(top/PX_PER_MIN)+DAY_START_MIN); let endMin=snap(Math.round((top+h)/PX_PER_MIN)+DAY_START_MIN+14); if(endMin<=startMin) endMin=startMin+15; if(endMin>DAY_END_MIN) endMin=DAY_END_MIN; sel.classList.toggle('conflict', hasConflict(tl,startMin,endMin,null)); }}); tl.addEventListener('mousedown',e=>{if(e.target!==tl)return; startY=e.offsetY; sel.classList.remove('d-none'); sel.classList.remove('conflict'); sel.style.top=startY+'px'; sel.style.height='2px';}); function finishSelection(e){ if(startY===null)return; const endY=e.offsetY; const topPx=Math.min(startY,endY); const bottomPx=Math.max(startY,endY); const startMin=snap(Math.round(topPx/PX_PER_MIN)+DAY_START_MIN); let endMin=snap(Math.round(bottomPx/PX_PER_MIN)+DAY_START_MIN+14); if(endMin<=startMin)endMin=startMin+15; if(endMin>DAY_END_MIN)endMin=DAY_END_MIN; const conflict=hasConflict(tl,startMin,endMin,null); sel.classList.add('d-none'); startY=null; if(conflict){ return; } const date=tl.dataset.date; const employe=tl.dataset.employe; $('#date').val(date); $('#start').val(minutesToTime(startMin)); $('#end').val(minutesToTime(endMin)); $(":checkbox").prop('checked',false).parent().removeClass('active'); $('input[type="checkbox"][value="'+employe+'"]').prop('checked',true).parent().addClass('active'); $('#taskModal').modal('show'); } tl.addEventListener('mouseup',finishSelection); tl.addEventListener('mouseleave',e=>{ if(startY!==null) finishSelection(e); }); tl.addEventListener('dragover',e=>{ if(!window.__dragEv)return; e.preventDefault(); setDragTarget(tl); const tlRect=tl.getBoundingClientRect(); let newTop=e.clientY - tlRect.top - window.__dragOffsetY; if(newTop<0)newTop=0; const maxTopPx=(DAY_END_MIN-DAY_START_MIN-15)*PX_PER_MIN; if(newTop>maxTopPx)newTop=maxTopPx; const snappedStart=snap(Math.round(newTop/PX_PER_MIN)+DAY_START_MIN); window.__dragCurrentTopPx=(snappedStart-DAY_START_MIN)*PX_PER_MIN; window.__dragEv.style.top=window.__dragCurrentTopPx+'px'; // Konflikt Vorschau
                        const dur = timeStrToMin(window.__dragEv.dataset.end)-timeStrToMin(window.__dragEv.dataset.start); const endMin = snappedStart + dur; const conflict = hasConflict(tl,snappedStart,endMin,window.__dragEv.id); window.__dragEv.classList.toggle('conflict-preview', conflict); }); tl.addEventListener('dragleave',e=>{ if(window.__dragTarget===tl){ tl.classList.remove('drag-target'); }}); });
            // Drag & Drop
            window.__dragEv=null; window.__dragOffsetY=0; window.__dragTarget=null; window.__dragCurrentTopPx=0; function setDragTarget(tl){ if(window.__dragTarget===tl) return; if(window.__dragTarget) window.__dragTarget.classList.remove('drag-target'); window.__dragTarget=tl; tl.classList.add('drag-target'); }
            document.addEventListener('dragstart',e=>{ const el=e.target.closest('.roster-event'); if(!el)return; window.__dragEv=el; el.classList.add('dragging'); el.classList.remove('conflict','conflict-preview'); const rect=el.getBoundingClientRect(); window.__dragOffsetY=e.clientY-rect.top; window.__dragCurrentTopPx=parseFloat(el.style.top)||0; window.__dragTarget=el.closest('.timeline'); window.__dragTarget.classList.add('drag-target'); });
            document.addEventListener('dragend',e=>{ if(!window.__dragEv) return; const tl=window.__dragTarget||window.__dragEv.closest('.timeline'); tl.classList.remove('drag-target'); const startMin=snap(Math.round(window.__dragCurrentTopPx/PX_PER_MIN)+DAY_START_MIN); const dur = timeStrToMin(window.__dragEv.dataset.end)-timeStrToMin(window.__dragEv.dataset.start); const endMin=startMin+dur; const conflict=hasConflict(tl,startMin,endMin,window.__dragEv.id); const evEl=window.__dragEv; if(conflict){ // revert
                    evEl.classList.remove('dragging'); evEl.classList.add('conflict'); evEl.style.top=topFromTimeStr(evEl.dataset.start)+'px'; window.__dragEv=null; window.__dragTarget=null; return; }
                const startStr=minutesToTime(startMin); const employeId=tl.dataset.employe; const dateStr=tl.dataset.date; $.ajax({type:'PATCH', url:'{{url('tasks/update')}}', data:{_token:'{{csrf_token()}}', _method:'PATCH', employe_id:employeId, task: evEl.id, start:startStr, date:dateStr}, success:function(resp){ evEl.classList.remove('dragging','conflict-preview'); if(evEl.dataset.employe!=employeId){ tl.appendChild(evEl); } evEl.dataset.employe=employeId; evEl.dataset.date=dateStr; evEl.dataset.start=resp.start; evEl.dataset.end=resp.end; evEl.style.top=topFromTimeStr(resp.start)+'px'; evEl.style.height=heightFromTimes(resp.start,resp.end)+'px'; evEl.classList.toggle('conflict', !!resp.conflict); }, error:function(){ evEl.classList.add('conflict'); }, complete:function(){ window.__dragEv=null; window.__dragTarget=null; }}); });
        })();
    </script>
@endpush
