<div class="card">
    <div class="card-body">
        <form action="{{url('types')}}" method="post" class="form form-inline">
            @csrf

                <div class="col-md-8 col-sm-12">
                    <div class="form-group">
                        <label for="news">
                            Bezeichnung*
                        </label>
                        <input type="text" id="type" name="type" class="form-control col-10" value="{{old('type')}}">
                    </div>
                </div>
                <div class="col-auto">
                    <label for="submit">
                    </label>
                    <button id="submit" type="submit" class="btn btn-primary btn-block">
                        speichern
                    </button>
                </div>
        </form>
    </div>
</div>
