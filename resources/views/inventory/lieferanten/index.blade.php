@extends('layouts.app')

@section('content')
    <div class="card">
        <div class="card-body">
            <a href="{{url('inventory/lieferanten/create')}}" class="btn btn-simple">
                neuer Lieferant
            </a>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h5>
                bestehende Lieferanten
            </h5>
        </div>
        <div class="card-body">
            <table class="table table-hover" id="lieferantenTable">
                <thead>
                <tr>
                    <th>Kürzel</th>
                    <th>Name</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($lieferanten as $lieferant)
                    <tr>
                        <td>
                            {{$lieferant->name}}
                        </td>
                        <td>
                            {{$category->kuerzel}}
                        </td>
                        <td>
                            <a href="{{url('inventory/lieferanten/'.$lieferant->id.'/edit')}}" title="{{$lieferant->name}} bearbeiten">
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
            $('#lieferantenTable').DataTable();
        } );
    </script>

@endpush

@section('css')
    <link href="//cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css" rel="stylesheet" />

@endsection

@push('modals')
@endpush
