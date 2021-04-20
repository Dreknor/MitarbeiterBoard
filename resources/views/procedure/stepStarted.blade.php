<div class="step_{{$step->parent}} @if($step->parent != "" and $step->parent_rel->done != 1) collapse @endif" id="step_{{$step->parent}}">
    <li class="list-group-item d-inline-flex border-0 ">
        @if ($step->parent != "" )
            <div class="d-inline pt-5 pr-1 align-middle">
                <i class="fa fa-arrow-alt-circle-right align-self-stretch"></i>
            </div>
        @endif
        <div class=" d-inline-flex">
            <div class="card @if( $step->done == 1) bg-success @else border border-info @endif">
                <div class="card-body">
                    <p class="font-weight-bold">
                        {{$step->name}}
                    </p>

                    @if($step->done == 0 and count($step->childs) > 0)
                        <a class="d-inline pull-right btn-link step_{{$step->parent}}" title="mehr Schritte einblenden" data-toggle="collapse" href=".step_{{$step->id}}">
                            <i class="fa fa-plus-circle pt-0"></i>
                        </a>
                    @endif

                    @if($step->done == 0)
                        <p class="small">
                            {{$step->description}}
                        </p>
                        @if($step->endDate)
                            <p class="">
                                zu erledigen bis: <br>
                                {{$step->endDate->format('d.m.Y')}}
                            </p>
                        @endif
                        <p class="small">
                        <ul class="list-group small">
                            @foreach($step->users as $user)
                                <li class="list-group-item">
                                    {{$user->name}}
                                    @if(count($step->users) > 1 and $step->done !=1)
                                        <div class="pull-right">
                                            <a href="{{url('procedure/step/'.$step->id.'/remove/'.$user->id)}}" class="card-link">
                                                <i class="fas fa-user-minus"></i>
                                            </a>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                            <li class="list-group-item">
                                <a href="#" class="card-link addUser" data-toggle="modal" data-target="#addUserModal"  data-step="{{$step->id}}">
                                    Person hinzufügen
                                </a>
                            </li>
                        </ul>
                        </p>
                        @if($step->users->contains(auth()->user()) and $step->done  == 0 and $step->endDate != null)
                            <div class="card-footer text-center">
                                <form action="{{url('procedure/step/'.$step->id.'/done')}}" method="post" class="form-horizontal">
                                    @csrf
                                    @method('put')

                                    <button type="submit" class="btn btn-success">
                                        Aufgabe erledigt
                                    </button>
                                </form>
                            </div>
                        @endif

                    @else
                        <p class="small">
                            erledigt: {{$step->updated_at->format('d.m.Y')}}
                        </p>
                    @endif
                </div>

            </div>
        </div>

        @if (count($step->childs) > 0)
            <div class="d-inline h-100 ">
                <ul class="list-group ">
                    @each('procedure.stepStarted',$step->childs, 'step')
                </ul>
            </div>
        @endif
    </li>
</div>

