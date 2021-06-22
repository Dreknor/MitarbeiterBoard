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
                <div class="col">
                    <form class="form-inline d-none" id="printForm" method="post" action="{{url('inventory/items/print')}}">
                        @csrf
                        <button type="submit" class="btn btn-simple btn-info pull-right" id="druckenBtn">Etikett drucken</button>
                                <label for="spalte">Spalte</label>
                                <input type="number" name="spalte" min="1" step="1" value="1" id="spalte" class="form-control" size="10">
                                <label for="spalte">Reihe</label>
                                <input type="number" name="reihe" min="1" step="1" value="1" id="reihe" class="form-control" size="10">
                    </form>
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
                    <td></td>
                    <th>Raum</th>
                    <th>Name</th>
                    <th>Beschreibung</th>
                    <th>Kategorie</th>
                    <th>Anschaffung am</th>
                    <th>Preis</th>
                    <th>Status</th>
                    <th>Update</th>
                    <th></th>
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
                <tr>
                    <td colspan="10">
                        @if(method_exists($items, 'links'))
                            {{$items->links()}}
                        @endif
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('js')
    <script src="//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready( function () {
            $('#itemTable').DataTable();
        } );


        $(':checkbox').change(function() {
            var array = [];
            $("input:checked").each(function() {
                array.push($(this).val());
            });

            console.log(array)

            if (array.length > 0){
                $('#printForm').removeClass('d-none');
            } else {
                $('#printForm').addClass('d-none');
            }
        });

    </script>

@endpush

@section('css')
    <link href="//cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css" rel="stylesheet" />

@endsection

