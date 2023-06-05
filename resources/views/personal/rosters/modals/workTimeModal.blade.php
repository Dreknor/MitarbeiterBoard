<div class="modal fade" id="workTimeModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Arbeitszeit</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="{{url('working_time')}}" method="post" class="form-vertical" id="WorkingTimeForm">
                    {{csrf_field()}}
                    <input type="hidden" name="roster_id" value="{{$roster->id}}">
                    <input type="hidden" name="employe_id" id="working_time_employe_id" value="">
                    <div class="">
                        <label for="date">Datum</label>
                        <input type="date" id="WorkingTimeDate" name="date" class="form-control" required>
                    </div>
                    <div class="">
                        <label for="start">Anfang</label>
                        <input type="time" id="working_time_start" name="start" class="form-control">
                    </div>
                    <div class="">
                        <label for="end">Ende</label>
                        <input type="time" id="working_time_end" name="end" class="form-control">
                    </div>
                    <div class="">
                        <label for="function">Aufgabe</label>
                        <input type="text" id="working_time_function" name="function" class="form-control">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success" form="WorkingTimeForm">Speichern</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
