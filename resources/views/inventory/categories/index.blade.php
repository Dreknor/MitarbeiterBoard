@extends('layouts.app')

@section('content')
    <div class="card">
        <div class="card-body">
            <a href="{{url('inventory/categories/create')}}" class="btn btn-simple">
                neue Kategorie
            </a>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h5>
                bestehende Kategorien
            </h5>
        </div>
        <div class="card-body">
            <table class="table table-hover" id="categoriesTable">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>gehört zu</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($categories as $category)
                    <tr>
                        <td>
                            {{$category->name}}
                        </td>
                        <td>
                            {{optional($category->parent)->name}}
                        </td>
                        <td>
                            <a href="{{url('inventory/categories/'.$category->id.'/edit')}}" title="{{$category->name}} bearbeiten">
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
            $('#categoriesTable').DataTable();
        } );
    </script>

@endpush

@section('css')
    <link href="//cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css" rel="stylesheet" />

@endsection

@push('modals')
@endpush
