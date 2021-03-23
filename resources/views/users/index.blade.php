@extends('layouts.app')

@section('content')

    <div class="container-fluid">
        <div class="card">
            <div class="card-header border-bottom">
                <div class="row">
                    <div class="col">
                        <h5 class="card-title">
                            Benutzerkonten
                        </h5>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <a href="{{url('users/create')}}" class="btn">Benutzer erstellen</a>
                <a href="{{url('importuser')}}" class="btn">Benutzer importieren</a>
            </div>
            <div class="card-body">
                <table class="table table-hover" id="userTable">
                    <thead>
                        <tr>
                            <td></td>
                            <th>Name</th>
                            <th>E-Mail</th>
                            <th>Rollen</th>
                            <th>Rechte</th>
                            <td></td>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td>
                                    <a href="{{url('/users/').'/'.$user->id}}" class="btn-link">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                                <td>
                                    {{$user->name}}
                                </td>
                                <td>
                                    {{$user->email}}
                                </td>
                                <td>
                                    @foreach($user->roles as $role)
                                        <div class="btn btn-outline-warning btn-sm">
                                            {{$role->name}}
                                        </div>
                                    @endforeach
                                </td>

                                <td>
                                    @foreach($user->permissions as $permission)
                                        <div class="btn btn-outline-danger btn-sm">
                                            {{$permission->name}}
                                        </div>
                                    @endforeach
                                </td>
                                <td>

                                    @can('logInAs')
                                        <a href="{{url("showUser/$user->id")}}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    @endcan
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
 <script>
     $(document).ready( function () {
         $('#userTable').DataTable();
     } );
 </script>

@endpush

@section('css')
    <link href="//cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css" rel="stylesheet" />

@endsection
