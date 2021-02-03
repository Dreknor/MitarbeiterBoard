<div class="card-body">
    <div class="container-fluid">
        <div class="row">
            @foreach($positions as $position)
                <div class="col-auto">
                    <div class="card border bg-light p-2">
                        <div class="card-header">
                            <h6>
                                {{$position->name}}
                            </h6>
                        </div>
                        <div class="card-body ">
                            @if(count($position->users)>0)
                                <p class="bold">
                                Zugeordnete Personen
                                </p>
                                <ul class="list-group">
                                    @foreach($position->users as $user)
                                        <li class="list-group-item">
                                            {{$user->name}}
                                            <div class="pull-right ">
                                                <a href="{{url('procedure/positions/'.$position->id.'/remove/'.$user->id)}}" class="text-danger">
                                                    <i class="fas fa-user-minus"></i>
                                                </a>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="">
                                    Position ist unbesetzt
                                </p>
                            @endif
                        </div>
                        <div class="card-footer">
                            <b>Person hinzufügen</b>
                            <form action="{{url('procedure/positions/'.$position->id.'/add')}}" method="post" class="form-inline">
                                @csrf
                                <div class="input-group">
                                    <select name="person_id" class="custom-select">
                                        <option></option>
                                        @foreach($users as $user)
                                            <option value="{{$user->id}}">
                                                {{$user->name}}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-success">
                                        <i class="far fa-save"></i>
                                    </button>
                                </div>

                            </form>

                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
