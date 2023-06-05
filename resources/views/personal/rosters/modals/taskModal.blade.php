<div class="modal fade" id="taskModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Neuer Termin</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="{{url('tasks/'.$roster->id)}}" method="post" class="form-vertical" id="newTask">
                    {{csrf_field()}}
                    <div class="">
                        <label for="event">Name des Termin</label>
                        <input type="text" id="event" name="event" class="form-control" autofocus required>
                    </div>
                    <div class="">
                        <label for="date">Datum</label>
                        <input type="date" id="date" name="date" class="form-control">

                    </div>
                    <div class="">
                        <label for="start">Startzeit (hh:mm)</label>
                        <input type="time" id="start" name="start" class="form-control">

                    </div>
                    <div class="">
                        <label for="end">Endzeit (hh:mm)</label>
                        <input type="time" id="end" name="end" class="form-control">
                    </div>
                    <div class="">
                        @foreach($employes as $employe)
                            <div class="col-auto">
                                <label class="form-check-label" for="flexCheckDefault">
                                    <input class="form-check-input" type="checkbox" name="employes[]"
                                           id="employe_check_{{$employe->id}}" value="{{$employe->id}}">

                                    {{$employe->vorname}}
                                </label>

                            </div>
                        @endforeach
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success" form="newTask">Save changes</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
