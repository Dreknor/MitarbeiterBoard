<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5>
                Plan importieren
            </h5>
        </div>
        <div class="card-body">
            <p class="card-text">
                Es besteht die Möglichkeit, einen Plan aus Indiware zu importieren. Dazu muss die Export-Gesamt XML-Datei aus Indiware ausgewählt werden.
                Voraussetzung ist, dass sowohl die Zeitraster, als auch die Räume in Indiware angelegt und beim Export ausgewählt wurden.
            <br>
        </div>
        <div class="card-body">
            <form action="{{url('rooms/import')}}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="form-row mb-2">
                   <label class="label w-100" data-toggle="tooltip" title="Sollen nicht vorhandene Räume erstellt werden?">
                        Räume erstellen? <i class="fa fa-question-circle"></i>
                    </label>
                    <select class="form-control" name="create_rooms">
                        <option value="0">Nein</option>
                        <option value="1">Ja</option>
                    </select>
                </div>
                <div class="form-row mb-2">
                    <label class="label w-100" data-toggle="tooltip" title="Soll der Plan vor dem Import geleert werden? Dies löscht alle vorhandenen Einträge">
                        Plan leeren? <i class="fa fa-question-circle"></i>
                    </label>
                    <select class="form-control" name="deletePlan">
                        <option value="0" selected>Nein</option>
                        <option value="1">Ja</option>
                    </select>
                </div>

                <div class="form-group row">
                    <label for="file" class="col-md-4 col-form-label text-md-right">Datei</label>
                    <div class="col-md-6">
                        <input type="file" name="file" id="file" class="customFile" accept="text/xml">
                    </div>
                </div>
                <div class="form-group
                    row mb-0">
                    <div class="col-md-6 offset-md-4">
                        <button type="submit" class="btn btn-primary">
                            Importieren
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
