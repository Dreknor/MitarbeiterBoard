@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h6 class="card-title">
                Räume importieren
            </h6>
        </div>
        <div class="card-body">
            <form action="{{url('inventory/locations/import')}}" method="post" class="form form-horizontal" enctype="multipart/form-data">
                @csrf
                <div class="form-row">
                        <div class="">
                            <input type="file"  name="file" id="customFile" accept=".xls,.xlsx">
                        </div>
                </div>
                <div class="form-row">
                    <p>Bitte die genaue Bezeichnung für die einzelnen Spalten angeben. Leer lassen, wenn nicht vorhanden.</p>
                </div>
                <div class="form-row">
                    <label for="kennzeichen">
                        Kennzeichen
                    </label>
                    <input type="text" id="kennzeichen" name="kennzeichen" class="form-control">
                </div>
                <div class="form-row">
                    <label for="name">
                        Name <i class="text-danger">(benötigt)</i>
                    </label>
                    <input type="text" id="name" name="name" class="form-control">
                </div>
                <div class="form-row">
                    <label for="description">
                        Beschreibung
                    </label>
                    <input type="text" id="description" name="description" class="form-control">
                </div>
                <div class="form-row">
                    <label for="user">
                        Verantwortlicher
                    </label>
                    <input type="text" id="user" name="user" class="form-control">
                </div>
                <div class="form-row">
                    <label for="type">
                        Typ
                    </label>
                    <input type="text" id="type" name="type" class="form-control">
                </div>
                <!-- ToDo
                <div class="form-row">
                    <label for="newType">
                        Sollen unbekannte Raum-Typen angelegt werden?
                    </label>
                    <select id="newType" name="newType" class="custom-select">
                        <option value="new">Typ neu anlegen</option>
                        <option value="setull">Typ nicht zuordnen</option>
                    </select>
                </div>
                -->
                <div class="form-row">
                    <button type="submit" class="btn btn-primary btn-block">
                            Import starten
                        </button>
                </div>
            </form>

        </div>
    </div>
</div>


@endsection

@push('css')

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/css/fileinput.min.css" media="all" rel="stylesheet" type="text/css" />

@endpush

@push('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/piexif.min.js" type="text/javascript"></script>



    <!-- piexif.min.js is needed for auto orienting image files OR when restoring exif data in resized images and when you
        wish to resize images before upload. This must be loaded before fileinput.min.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/piexif.min.js" type="text/javascript"></script>
    <!-- sortable.min.js is only needed if you wish to sort / rearrange files in initial preview.
        This must be loaded before fileinput.min.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/sortable.min.js" type="text/javascript"></script>
    <!-- purify.min.js is only needed if you wish to purify HTML content in your preview for
        HTML files. This must be loaded before fileinput.min.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/purify.min.js" type="text/javascript"></script>
    <!-- popper.min.js below is needed if you use bootstrap 4.x (for popover and tooltips). You can also use the bootstrap js
       3.3.x versions without popper.min.js. -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/fileinput.min.js"></script>
    <!-- following theme script is needed to use the Font Awesome 5.x theme (`fas`) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/themes/fas/theme.min.js"></script>

    <script>
        // initialize with defaults

        $("#customFile").fileinput({
            'showUpload':false,
            'previewFileType':'any',
            maxFileSize: 3000,
            'theme': "fas",
            "allowedFileExtensions": ['xls', 'xlsx']
        });
    </script>


@endpush
