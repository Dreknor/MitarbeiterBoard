<div class="modal fade" id="trashDayModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tag löschen?</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="{{url('roster/'.$roster->id.'/trashDay')}}" method="post" class="form-vertical"
                      id="trashDayForm">
                    {{csrf_field()}}
                    @method('delete')
                    <input type="hidden" name="date" id="trashDate" value="">
                    <input type="hidden" name="roster_id" id="" value="{{$roster->id}}">
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-danger" form="trashDayForm">Tag leeren</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
