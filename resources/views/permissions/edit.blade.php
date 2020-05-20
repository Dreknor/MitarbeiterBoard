@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header border-bottom">
                        <h5 class="card-title">
                            Rollen und Rechte
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive-sm">
                            <form class="form-horizontal" method="post" action="{{url('roles')}}">
                                @csrf
                                @method ('put')
                                <table class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th>Rechte</th>
                                        <th>Rolle</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($permissions as $permission)
                                        <tr>
                                            <td>
                                                {{$permission->name}}
                                            </td>
                                            <td class="row">
                                                @foreach($roles as $role)
                                                    <div class="col-lg-2 col-md-4 col-sm-6">
                                                        <input type="checkbox" name="{{$role->name}}[]" value="{{$permission->name}}" @if($role->hasPermissionTo($permission->name)) checked @endif> {{$role->name}}
                                                    </div>
                                                @endforeach
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                    <tfoot>
                                    <tr>
                                        <td colspan="2">
                                            <div class="col-12">
                                                <button type="submit" class="btn btn-success btn-block collapse" id="btn-save">speichern</button>
                                            </div>
                                        </td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header border-bottom">
                        <h5 class="card-title">
                            neue Rolle anlegen
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{url('roles')}}" class="form-horizontal" method="post">
                            @csrf
                            <input type="text" name="name" placeholder="Rollenname" class="form-control">
                            <button type="submit" class="btn btn-success btn-block">Rolle anlegen</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header border-bottom">
                        <h5 class="card-title">
                            neues Recht anlegen
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{url('roles/permission')}}" class="form-horizontal" method="post">
                            @csrf
                            <input type="text" name="name" placeholder="Names des Rechtes" class="form-control">
                            <button type="submit" class="btn btn-success btn-block">Berechtigung anlegen</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')

    <script>
        $(document).ready(function () {
            $('input[type="checkbox"]').change(function () {
                $(this).closest("form").submit();
            });

        });

    </script>

@endpush
