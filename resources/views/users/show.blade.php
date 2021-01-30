@extends('layouts.app')

@section('content')
    <form action="{{url('/users/').'/'.$user->id}}" method="post" class="form form-horizontal">
        @csrf
        @method('PUT')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header border-bottom">

                <h5 class="card-title">
                    {{$user->name}}
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-5 col-sm-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    Benutzer-Einstellungen
                                </h5>
                            </div>

                            <div class="card-body">
                                @if ($errors->any())
                                    <div class="alert alert-danger">
                                        <ul>
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                    <div class="row">
                                        <div class="col-lg-12 col-md-12 col-sm-12">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <input type="text" class="form-control border-input" placeholder="Name" name="name" value="{{$user->name}}" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>E-Mail</label>
                                                <input type="text" class="form-control border-input" placeholder="E-Mail" name="email" value="{{$user->email}}" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>Muss Passwort ändern</label>
                                                <select class="custom-select" name="changePassword">
                                                    <option value="1" @if($user->changePassword)selected @endif>Ja</option>
                                                    <option value="0" @if(!$user->changePassword)selected @endif>Nein</option>
                                                </select>
                                            </div>

                                        </div>
                                    </div>
                                    @can('set password')
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label>neues Passwort</label>
                                                    <input class="form-control" name="new-password" type="password" minlength="8">
                                                </div>

                                            </div>
                                        </div>
                                    @endcan

                                    <div class="row">
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-success btn-block collapse" id="btn-save">speichern</button>
                                        </div>
                                    </div>

                            </div>
                        </div>

                    </div>

                    @can('edit groups')
                        <div class="col-md-2 col-sm-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        Gruppen
                                    </h5>
                                </div>
                                <div class="card-body">
                                    @foreach($groups as $group)
                                        <div>
                                            <input type="checkbox" id="{{$group->name}}" name="groups[]" value="{{$group->id}}" @if($user->groups()->contains('id', $group->id)) checked @endif>
                                            <label for="{{$group->name}}">{{$group->name}}</label>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="card-footer">

                                </div>
                            </div>
                        </div>
                    @endcan
                    @can('edit permissions')
                        <div class="col-md-2 col-sm-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        Rollen
                                    </h5>
                                </div>
                                <div class="card-body">
                                    @foreach($roles as $role)
                                        <div>
                                            <input type="checkbox" id="{{$role->name}}" name="roles[]" value="{{$role->name}}" @if($user->hasRole($role->name)) checked @endif>
                                            <label for="{{$role->name}}">{{$role->name}}</label>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="card-footer">

                                </div>
                            </div>
                        </div>

                    <div class="col-md-2 col-sm-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    indiv. Rechte
                                </h5>
                            </div>
                            <div class="card-body">
                                    @foreach($permissions as $permission)
                                        <div>
                                            <input type="checkbox" id="{{$permission->name}}" name="permissions[]" value="{{$permission->name}}" @if($user->hasDirectPermission($permission->name)) checked @endif>
                                            <label for="{{$permission->name}}">{{$permission->name}}</label>
                                        </div>
                                    @endforeach
                            </div>
                        </div>
                    </div>
                    @endcan
                </div>

            </div>
        </div>
    </div>
    </form>

    <div class="container-fluid">
        <div class="pull-right">
            <form method="post" action="{{url("/users/".$user->id)}}">
                @csrf
                @method('delete')

                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-user-slash"></i> Benutzer endgültig löschen
                </button>
            </form>
        </div>
    </div>

@endsection

@push('js')

    <script>
        $(document).ready(function () {


            $("input").keyup(function() {
                checkChanged();
            });
            $("select").change(function() {
                checkChanged();
            });

            $(":checkbox").change(function() {
                checkChanged();
            });

            function checkChanged() {

                if (!$('input').val()) {
                    $("#btn-save").hide();
                } else {
                    $("#btn-save").show();
                }
            }
        });

    </script>

@endpush
