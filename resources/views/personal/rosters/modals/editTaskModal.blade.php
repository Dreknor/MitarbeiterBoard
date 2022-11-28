<div class="modal fade" id="editTaskModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Termin bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="" method="post" class="form-vertical" id="editTaskForm">
                    {{csrf_field()}}
                    @method('put')
                    <div class="">
                        <label for="event">Name des Termin</label>
                        <input type="text" id="editEvent" name="event" class="form-control" autofocus required>
                    </div>
                    <div class="">
                        <label for="date">Datum</label>
                        <input type="date" id="editDate" name="date" class="form-control">

                    </div>
                    <div class="">
                        <label for="start">Startzeit (hh:mm)</label>
                        <input type="time" id="editStart" name="start" class="form-control">

                    </div>
                    <div class="">
                        <label for="end">Endzeit (hh:mm)</label>
                        <input type="time" id="editEnd" name="end" class="form-control">
                    </div>
                    <div class="">
                        @foreach($employes as $employe)
                            <div class="col-auto">
                                <label class="form-check-label" for="flexCheckDefault">
                                    <input class="form-check-input" type="checkbox" name="employes[]"
                                           id="edit_employe_check_{{$employe->id}}" value="{{$employe->id}}">
                                    {{$employe->vorname}}
                                </label>

                            </div>
                        @endforeach
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success" form="editTaskForm">Save changes</button>
                <a href="" class="btn btn-info" id="rememberEvent">Merken</a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <form action="" method="post" id="delteTaskForm">
                    @csrf
                    @method('delete')
                    <button type="submit" @class('btn btn-danger')>
                        <i @class('la la-trash')>
                        </i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
