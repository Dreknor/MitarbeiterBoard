@extends('layouts.app')

@section('content')
    <div class="card">
        <div class="card-body">
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
                    <th>Anschaffungspreis</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>
                            <input type="checkbox" name="selected" value="{{$item->id}}" class="custom-checkbox">
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
    </script>

@endpush

@section('css')
    <link href="//cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css" rel="stylesheet" />

@endsection

