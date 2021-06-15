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
                            {{$item->date->format('d.m.Y')}}
                        </td>
                        <td>
                            {{number_format($item->price, 2)}} €
                        </td>
                        <td>
                            <a href="{{url('inventory/locations/'.$item->id.'/edit')}}" title="{{$item->name}} bearbeiten">
                                <i class="fas fa-edit"></i>
                            </a>
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
            $('#locationTable').DataTable();
        } );
    </script>

@endpush

@section('css')
    <link href="//cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css" rel="stylesheet" />

@endsection

@push('modals')
    <div class="modal fade" id="typeModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg " role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Typ hinzufügen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{url('inventory/locationtype')}}" method="post" class="form-horizontal" id="typeForm">
                        @csrf
                        <div class="form-row p-2">
                            <label for="name">Bezeichnung</label>
                            <input type="text" name="name"  value="{{old('name')}}" class="form-control" required autofocus>
                        </div>
                        <div class="form-row p-2">
                            <label for="task">Beschreibung</label>
                            <input type="text" name="description" value="{{old('description')}}" class="form-control">
                        </div>

                    </form>
                </div>
                <div class="modal-footer">
                    <button type="submit"  form="typeForm" class="btn btn-primary">Speichern</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endpush
