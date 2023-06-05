@extends('layouts.app')

@section('title')
    Mitarbeiter
@endsection

@section('site-title')
    Mitarbeiterverwaltung
@endsection


@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header border-bottom">
                <div class="row">
                    <div class="col">
                        <h5 class="card-title d-inline">
                            Mitarbeiter wählen
                        </h5>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-hover" id="userTable">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Nachname</th>
                            <th>Vorname</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($employes as $employe)
                            <tr>
                                <td>
                                    <a href="{{url('/timesheets/').'/'.$employe->id}}">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                </td>
                                <td>
                                    {{$employe->familienname}}
                                </td>
                                <td>
                                    {{$employe->vorname}}
                                </td>
                                <td>

                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        </div>

@endsection

@push('js')
 <script src="//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
 <script src="//cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>
 <script>
     $(document).ready( function () {
         $('#userTable').DataTable();
     } );
 </script>

@endpush

@section('css')
    <link href="//cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css" rel="stylesheet" />
    <link href="//cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css" rel="stylesheet" />
    <link href="//cdn.datatables.net/buttons/1.7.1/css/buttons.bootstrap4.min.css" rel="stylesheet" />

@endsection
