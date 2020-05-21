@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card-deck">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        neue Gruppe anlegen
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{url('groups')}}" method="post" class="form-horizontal">
                        @csrf
                        <div class="form-row">
                            <label for="name">Name der neuen Gruppe</label>
                            <input type="text" class="form-control" name="name" id="name" required autofocus>
                        </div>
                        <div class="form-row">
                            <button type="submit" class="btn btn-success btn-block">
                                speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @foreach($groups as $group)
                @if($loop->index%2 == 0)
                    <div class="w-100 d-none d-sm-block d-md-none"><!-- wrap every 2 on sm--></div>
                @elseif($loop->index%3 == 0)
                    <div class="w-100 d-none d-md-block d-lg-none"><!-- wrap every 3 on md--></div>
                @endif
                <div class="card m-1">
                    <div class="card-header">
                        <h5 class="card-title">
                            {{$group->name}}
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            @if($group->users->count() == 0)
                                <li class="list-group-item"> Keine Benutzer in der Gruppe </li>
                            @else
                                @foreach($group->users as $user)
                                    <li class="list-group-item">
                                        {{$user->name}}
                                    </li>
                                @endforeach
                            @endif
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
