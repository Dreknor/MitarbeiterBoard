<div class="card">
    <div class="card-body">
        <form action="{{url('vertretungen')}}" method="post" class="form form-horizontal">
            @csrf
            <div class="form-row">
                <div class="col-sm-12 col-md-6 col-lg-2">
                    <div class="form-group">
                        <label for="date">
                            Datum
                        </label>
                        <input type="date" min="{{\Carbon\Carbon::today()}}" id="date" name="date" required class="form-control" value="{{old('date')}}">
                    </div>
                </div>
                <div class="col-sm-12 col-md-3 col-lg-4">
                    <div class="form-group">
                        <label for="lehrer">
                            neuer Lehrer
                        </label>
                        <select name="users_id" id="lehrer" class="custom-select" >
                            <option></option>
                            @foreach($lehrer as $Lehrer)
                                <option value="{{$Lehrer->id}}">{{$Lehrer->name}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-sm-12 col-md-3 col-lg-5">
                    <div class="form-group">
                        <label for="klasse">
                            Klasse
                        </label>
                        <select name="klassen_id" id="klasse" class="custom-select" required>
                            <option class="disabled"></option>
                            @foreach($klassen as $klasse)
                                <option value="{{$klasse->id}}">{{$klasse->name}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="col-sm-12 col-md-3 col-lg-2">
                    <div class="form-group">
                        <label for="stunde">
                            Stunde
                        </label>
                        <input type="number" min="0" max="10" id="stunde" name="stunde" required class="form-control" value="{{old('date')}}">
                    </div>
                </div>
                <div class="col-sm-12 col-md-1 col-lg-1">
                    <div class="form-group">
                        <label for="Doppelstunde">
                            Doppelstunde
                        </label>
                        <select name="Doppelstunde" id="Doppelstunde" class="custom-select">
                            <option value="1">Ja</option>
                            <option value="0">Nein</option>

                        </select>
                    </div>
                </div>
                <div class="col-sm-12 col-md-2 col-lg-2">
                    <div class="form-group">
                        <label for="altFach">
                            altes Fach
                        </label>
                        <input type="text" max="10" id="altFach" name="altFach" required class="form-control" value="{{old('altFach')}}">
                    </div>
                </div>
                <div class="col-sm-12 col-md-2 col-lg-2">
                    <div class="form-group">
                        <label for="neuFach">
                            neues Fach
                        </label>
                        <input type="text" max="10" id="neuFach" name="neuFach"  class="form-control" value="{{old('neuFach')}}">
                    </div>
                </div>
                <div class="col-sm-12 col-md-3 col-lg-4">
                    <div class="form-group">
                        <label for="comment">
                            Bemerkung
                        </label>
                        <input type="text" id="comment" name="comment" maxlength="60" class="form-control" value="{{old('comment')}}">

                    </div>
                </div>
                <div class="col-sm-12 col-md-3 col-lg-1">
                    <label for="submit">

                    </label>
                    <button id="submit" type="submit" class="btn btn-primary btn-block">
                        planen
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>
