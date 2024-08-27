<form action="{{url('klassen')}}" method="post">
    @csrf
    <div class="row">
        <div class="col-sm-12 col-md-4 col-4">
            <div class="form-group">
                <label for="name">Klassenname</label>
                <input type="text" class="form-control" placeholder="Klassenname" name="name" required>

            </div>
        </div>
        <div class="col-sm-12 col-md-2 col-2">
            <div class="form-group">
                <label for="kuerzel">Kürzel</label>
                <input type="text" class="form-control" placeholder="Kürzel" name="kuerzel" required>

            </div>
        </div>
        <div class="col-sm-12 col-md-3 col-2">
            <label></label>
            <button type="submit" class="btn btn-success btn-block">anlegen</button>
        </div>
    </div>

</form>
