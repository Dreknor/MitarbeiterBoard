<div class="card">
    <div class="card-body">
        <form action="{{url('vertretungen/'.$vertretung->id)}}" method="post" class="form form-horizontal">
            @csrf
            @method('put')
            <div class="form-row">
                <div class="col-sm-12 col-md-6 col-lg-2">
                    <div class="form-group">
                        <label for="date">
                            Datum
                        </label>
                        <input type="date" min="{{\Carbon\Carbon::today()}}" id="date" name="date" required
                               class="form-control" value="{{old('date', $vertretung->date->format('Y-m-d'))}}">
                    </div>
                </div>
                <div class="col-sm-12 col-md-2 col-lg-3">
                    <div class="form-group">
                        <label for="type">
                            Typ
                        </label>
                        <select name="type" id="type" class="custom-select" >
                            <option></option>
                            <option value="Ausfall" @if($vertretung->type == "Ausfall") selected @endif>Ausfall</option>
                            <option value="Vertretung (fachgerecht)" @if($vertretung->type == "Vertretung (fachgerecht)") selected @endif >Vertretung (Fachgerecht)</option>
                            <option value="Vertretung (fachfremd)" @if($vertretung->type == "Vertretung (fachfremd)") selected @endif>Vertretung (Fachfremd)</option>
                            <option value="Aufgaben" @if($vertretung->type == "Aufgaben") selected @endif>Aufgaben</option>
                            <option value="Sonstiges" @if($vertretung->type == "Sonstiges") selected @endif>Sonstiges</option>
                        </select>
                    </div>
                </div>
                <div class="col-sm-12 col-md-2 col-lg-3">
                    <div class="form-group">
                        <label for="lehrer">
                            neuer Lehrer
                        </label>
                        <select name="users_id" id="lehrer" class="custom-select" >
                            <option></option>
                            @foreach($lehrer as $Lehrer)
                                <option value="{{$Lehrer->id}}" @if($vertretung->users_id == $Lehrer->id) selected @endif>{{$Lehrer->name}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-sm-12 col-md-2 col-lg-3">
                    <div class="form-group">
                        <label for="klasse">
                            Klasse
                        </label>
                        <select name="klassen_id" id="klasse" class="custom-select" required>
                            <option class="disabled"></option>
                            @foreach($klassen as $klasse)
                                <option value="{{$klasse->id}}"  @if($vertretung->klassen_id == $klasse->id) selected @endif>{{$klasse->name}}</option>
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
                        <input type="number" min="0" max="10" id="stunde" name="stunde" required class="form-control" value="{{old('stunde', $vertretung->getRawOriginal('stunde'))}}">
                    </div>
                </div>
                <div class="col-sm-12 col-md-1 col-lg-1">
                    <div class="form-group">
                        <label for="Doppelstunde">
                            Doppelstunde
                        </label>
                        <select name="Doppelstunde" id="Doppelstunde" class="custom-select">
                            <option value="1" @if($vertretung->Doppelstunde == 1) selected @endif>Ja</option>
                            <option value="0" @if($vertretung->Doppelstunde == 0) selected @endif>Nein</option>

                        </select>
                    </div>
                </div>
                <div class="col-sm-12 col-md-2 col-lg-2">
                    <div class="form-group">
                        <label for="altFach">
                            altes Fach
                        </label>
                        <input type="text" max="10" id="altFach" name="altFach" required class="form-control" value="{{old('altFach', $vertretung->altFach)}}">
                    </div>
                </div>
                <div class="col-sm-12 col-md-2 col-lg-2">
                    <div class="form-group">
                        <label for="neuFach">
                            neues Fach
                        </label>
                        <input type="text" max="10" id="neuFach" name="neuFach"  class="form-control" value="{{old('neuFach', $vertretung->neuFach)}}">
                    </div>
                </div>
                <div class="col-sm-12 col-md-3 col-lg-4">
                    <div class="form-group">
                        <label for="comment">
                            Bemerkung
                        </label>
                        <input type="text" id="comment" name="comment" maxlength="60" class="form-control" value="{{old('comment', $vertretung->comment)}}">

                    </div>
                </div>
            </div>
                <div class="form-row">
                    <div class="col-12">
                        <button id="submit" type="submit" class="btn btn-block btn-bg-gradient-x-blue-green">
                            aktualisieren
                        </button>
                    </div>
                </div>
        </form>
    </div>
</div>
