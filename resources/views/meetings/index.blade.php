@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card ">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h3 class="card-title">Meetings</h3>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createMeetingModal">
                Meeting erstellen
            </button>
        </div>
        <div class="card-body">
            <!-- Modal -->
            <div class="modal fade" id="createMeetingModal" tabindex="-1" role="dialog" aria-labelledby="createMeetingModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createMeetingModalLabel">Neues Meeting erstellen</h5>

                        </div>
                        <div class="modal-body">
                            <form action="{{route('meetings.store',['group' => $group->name])}}" method="POST" class="form-horizontal">
                                @csrf
                                <div class="form-row">
                                    <div class="col-md-6">
                                        <label for="title">Titel des Meetings</label>
                                        <input type="text" class="form-control" name="title" id="title" required autofocus>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="date">Datum</label>
                                        <input type="date" class="form-control" name="date" id="date" required min="{{Carbon\Carbon::now()->format('Y-m-d')}}">
                                    </div>
                                </div>
                                <div class="form-row mt-1">
                                    <div class="col-md-6">
                                        <label for="start_time">Startzeit</label>
                                        <input type="time" class="form-control" name="start_time" id="start_time" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="end_time">Endzeit</label>
                                        <input type="time" class="form-control" name="end_time" id="end_time" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary mt-3">Meeting erstellen</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Ende Modal -->
        </div>
    </div>
        @if($meetingsToday->count())
            @foreach($meetingsToday as $meeting)
                @include('meetings.elements.meeting', ['meeting' => $meeting, 'group' => $group])
            @endforeach
        @endif

        <div class="card bg-light">
            <div class="card-header">
                <h5 class="card-title">
                    Nächste Meetings
                </h5>
            </div>
            <div class="card-body">
                @if($otherMeetings->count())
                    @foreach($otherMeetings as $meeting)
                        @include('meetings.elements.meeting', ['meeting' => $meeting, 'group' => $group])
                    @endforeach
                @else
                    <p>Keine zukünftigen Meetings geplant.</p>
                @endif
        </div>
</div>
    <div class="mb-3">
        <a href="{{ route('meetings.past', ['groupname' => $group->name]) }}" class="btn btn-outline-secondary">
            Vergangene Meetings anzeigen
        </a>
    </div>
</div>
@endsection


@push('js')
    <script>
        $('input[type=range]').on("change", function() {
            let theme = $(this).data('theme');

            let url = "{{url(request()->segment(1).'/themes/' )}}"
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



    </script>
@endpush
