<div class="row">
    <div class="col-12">
        <div class="card">

            <div class="card-header">
                <h5>
                    Gruppenfilter
                </h5>
            </div>

            <div class="card-body">
                <div class="container-fluid">
                    <div class="form-row">
                        <div class="col-12">
                            <label for="group">Gruppe auswählen</label>
                            <select name="group" id="group_filter" class="form-control">
                                <option value="all" selected>Alle</option>
                                @foreach(auth()->user()->groups_rel as $group)
                                    <option value="{{$group->name}}">{{$group->name}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
