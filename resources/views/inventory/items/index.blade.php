@extends('layouts.app')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-auto">
                    @if($categories >0 and  $locations >0 and  $lieferanten >0)
                        <a href="{{url('inventory/items/create')}}" class="btn btn-simple">
                            neuer Gegenstand
                        </a>
                        <a href="{{url('inventory/items/import')}}" class="btn btn-simple">
                            Importieren
                        </a>
                    @else
                        <p class="alert alert-info">
                            Es müssen erst Lieferanten, Räume und Kategorien erstellt werden.
                        </p>
                    @endif
                </div>
                <div class="col-auto">
                    <form class="form-inline" id="searchForm" method="post" action="{{url('inventory/items/search')}}">
                        @csrf
                                <input type="text" name="search" placeholder="Gegenstand suchen" id="search" class="form-control">
                        <button type="submit" class="btn btn-simple btn-info pull-right" id="searchBtn">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="container-fluid">
                        <form class="form-inline d-none"  target="_blank" id="printForm" method="post" action="{{url('inventory/items/print')}}">
                            @csrf
                            <div class="form-row">
                                <div class="col-sm-12 col-md-3 ml-2">
                                    <label for="spalte">Spalte</label>
                                    <input type="number" name="spalte" min="1" step="1" value="1" id="spalte" class="form-control" >
                                </div>
                                <div class="col-sm-12 col-md-3 ml-2">
                                    <label for="reihe">Reihe</label>
                                    <input type="number" name="reihe" min="1" step="1" value="1" id="reihe" class="form-control" >
                                </div>
                                <div class="col-sm-12 col-md-3 ml-2">
                                    <label for="anzahl">Etiketten je Item</label>
                                    <input type="number" name="anzahl" min="1" step="1" value="1" id="anzahl" class="form-control" >
                                </div>
                                <div class="col-sm-12 col-md-2 ml-2 mt-2">
                                    <button type="submit" class="btn btn-simple btn-info form-control" id="druckenBtn">Etikett drucken</button>
                                </div>
                            </div>

                        </form>
                    </div>

                </div>

            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h5>
                Inventar
            </h5>
        </div>
        <div class="card-body">
            <table class="table table-hover" id="itemTable">
                <thead>
                <tr>
                    <td><i class="fa fa-check"></i> </td>
                    <th>Raum</th>
                    <th>Name</th>
                    <th>eigene Inv. - Nr.</th>
                    <th>Beschreibung</th>
                    <th>Kategorie</th>
                    <th>Anschaffung am</th>
                    <th>Preis</th>
                    <th>Status</th>
                    <th>Update</th>
                    <th><i class="fa fa-edit"></i> </th>
                </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td>
                                <input type="checkbox" name="selected[]" value="{{$item->uuid}}" class="custom-checkbox" form="printForm">
                            </td>
                            <td>
                                {{optional($item->location)->name}}
                            </td>
                            <td>
                                {{$item->name}}
                            </td>
                            <td>
                                <small>
                                    {{$item->oldInvNumber?:$item->uuid}}
                                </small>
                            </td>
                            <td>
                                <small>
                                    {{$item->description}}
                                </small>
                            </td>
                            <td>
                                {{optional($item->category)->name}}
                            </td>
                            <td>
                                {{optional($item->date)->format('d.m.Y')}}
                            </td>
                            <td>
                                {{number_format($item->price, 2)}} €
                            </td>
                            <td>
                                @switch($item->status)
                                    @case('defekt')
                                        <i class="far fa-thumbs-down text-danger">defekt</i>
                                    @break

                                    @case('abgenutzt')
                                        <i class="far fa-hand-point-right text-warning">gebraucht</i>
                                    @break

                                    @case('fehlt')
                                        <i class="far fa-question-circle text-danger"></i>
                                    @break

                                    @default
                                        <i class="far fa-thumbs-up text-success">neu</i>
                                @endswitch
                            </td>
                            <td>
                                {{$item->updated_at->format('Y-m-d')}}
                            </td>
                            <td>
                                <div class="row">
                                    <div class="col">
                                        <a href="{{url('inventory/items/'.$item->id.'')}}" title="{{$item->name}} bearbeiten">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>

                </tfoot>
            </table>
        </div>
    </div>
@endsection

@push('js')
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.1/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.1/js/dataTables.bootstrap4.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.0.0/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.0.0/js/buttons.html5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.0.0/js/buttons.print.min.js"></script>


    <script>
        $(document).ready(function () {
            $('#itemTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    { extend: 'copy', className: 'btn ' },
                    { extend: 'excel', className: 'btn' },
                    { extend: 'pdf', className: 'btn' },
                    { extend: 'print', className: 'btn' },
                ]
            } );
        });

        $(':checkbox').change(function() {
            var array = [];
            $("input:checked").each(function() {
                array.push($(this).val());
            });

            if (array.length > 0){
                $('#printForm').removeClass('d-none');
            } else {
                $('#printForm').addClass('d-none');
            }
        });

    </script>

@endpush

@section('css')
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.1/css/jquery.dataTables.min.css"/>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css"/>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css"/>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.0.0/css/buttons.dataTables.min.css"/>


@endsection

