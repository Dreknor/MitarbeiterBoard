@extends('layouts.app')

@section('content')
    <a href="{{url('inventory/items/'.$item->id)}}" class="btn btn-primary btn-link">zurück</a>

    <div class="card">
        <div class="card-header">
            <h5 class="d-inline">
                {{$item->name}} bearbeiten
            </h5>
            <form action="{{url('inventory/items/'.$item->uuid)}}" method="post" class="d-inline pull-right">
                @csrf
                @method('delete')
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-trash"></i>Löschen
                </button>
            </form>
        </div>
        <div class="card-body">
            <form action="{{url('inventory/items/'.$item->id)}}" method="post" class="form-horizontal"  enctype="multipart/form-data">
                @csrf
                @method('put')
                <div class="form-row">
                    <label for="status">
                        Zustand
                    </label>
                    <select name="status" id="status" class="custom-select">
                            <option value="neu"  @if($item->status == 'neu') selected @endif>neuwertig</option>
                            <option value="abgenutzt"  @if($item->status == 'abgenutzt') selected @endif>abgenutzt</option>
                            <option value="defekt"  @if($item->status == 'defekt') selected @endif>defekt</option>
                    </select>
                </div>
                <div class="form-row">
                    <label for="name">
                        Bezeichnung <i class="text-danger">(benötigt)</i>
                    </label>
                    <input type="text" name="name" id="name" class="form-control" value="{{old('name', $item->name)}}">
                </div>
                <div class="form-row">
                    <label for="description">Beschreibung</label>
                    <input type="text" name="description" id="description" class="form-control" value="{{old('description', $item->description)}}">
                </div>
                <div class="form-row">
                    <label for="oldInvNumber">eigene Inv.-Nr.</label>
                    <input type="text" name="oldInvNumber" id="oldInvNumber" class="form-control" value="{{old('oldInvNumber', $item->oldInvNumber)}}">
                </div>
                <div class="form-row">
                    <label for="category">
                        Kategorie <i class="text-danger">(benötigt)</i>
                    </label>
                    <select name="category_id" id="category" class="custom-select">
                        <option disabled selected></option>
                        @foreach($categories as $category)
                            <option value="{{$category->id}}" @if($item->category->id == $category->id) selected @endif>
                                {{$category->name}}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <label for="category">
                        Standort <i class="text-danger">(benötigt)</i>
                    </label>
                    <select name="location_id" id="category" class="custom-select">
                        <option disabled selected></option>
                        @foreach($locations as $location)
                            <option value="{{$location->id}}"  @if($item->location->id == $location->id) selected @endif>
                                {{$location->name}}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <label for="lieferant">
                        Lieferant
                    </label>
                    <select name="lieferant_id" id="lieferant" class="custom-select">
                        <option disabled selected></option>
                        @foreach($lieferanten as $lieferant)
                            <option value="{{$lieferant->id}}"  @if(optional($item->lieferant)->id == $lieferant->id) selected @endif>
                                {{$lieferant->name}}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <div class="col-sm-12 col-md-4 ">
                        <label for="date">Anschaffung am</label>
                        <input type="date" name="date" id="date" class="form-control" value="{{old('date', optional($item->date)->format('Y-m-d'))}}">
                    </div>
                    <div class="col-sm-12 col-md-4">
                        <label for="price">Preis</label>
                        <input type="number"  name="price" id="price" class="form-control" value="{{old('price', $item->price)}}" step="0.01">
                    </div>
                    <div class="col-sm-12 col-md-4">
                        <label for="number">Anzahl</label>
                        <input type="number"  name="number" id="number" class="form-control" value="{{old('number', $item->number)}}" step="1" required>
                    </div>
                </div>
                <div class="form-row ">
                    <div class="col-md-4 col-sm-12">
                        <label for="customFile">
                            Bild
                        </label>
                        <input type="file"  name="files[]" id="customFile" multiple>
                    </div>
                    <div class="col-md-4 col-sm-12 offset-md-4">
                        <label for="customFile">
                            Rechnung
                        </label>
                        <input type="file"  name="invoice[]" id="invoiceFile" multiple>
                    </div>
                </div>

                <button type="submit" class="btn btn-success btn-block">speichern</button>
            </form>
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
            'theme': "fas",
            'allowedFileTypes' : ['image'],
        });

        $("#invoiceFile").fileinput({
            'showUpload':false,
            'previewFileType':'any',
            'theme': "fas",
            'allowedFileTypes' : ['pdf', 'image'],
        });
    </script>


@endpush
