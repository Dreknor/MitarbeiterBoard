<div class="col-auto">
    <div class="row">
        <div class="col-auto mx-auto">
            <div class="card bg-light border @if($step->endDate != null and $step->done == 0) border-warning @elseif($step->done == 1) border-success @endif" data-step="{{$step->id}}">
                <div class="card-header @if($step->endDate != null and $step->done == 0) bg-warning @elseif($step->done == 1) bg-success @else @endif">
                    <h6>
                        {{$step->name}}
                        <div class="pull-right">

                        </div>
                    </h6>
                    <p class="small">
                        @if($step->parent != "")
                            nach: {{$step->parent_rel->name}}
                        @endif
                    </p>
                    <small>
                        {{$step->description}}
                    </small>
                </div>
                <div class="card-body">
                    <div class="container-fluid">
                        <div class="row mt-2">
                            <div class="col-12">
                                <b>
                                    Verantwortlich:
                                </b>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 ">
                               <ul class="list-group">
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
                                   @if($step->done !=1)
                                       <li class="list-group-item">
                                           <a href="#" class="card-link addUser" data-toggle="modal" data-target="#addUserModal"  data-step="{{$step->id}}">
                                               Person hinzufügen
                                           </a>
                                       </li>
                                   @endif
                               </ul>
                            </div>
                        </div>
                        <div class="row">

                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <b>
                                    @if($step->done)
                                        erledigt:
                                    @else
                                        zu erledigen bis:
                                    @endif

                                </b>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                @if($step->endDate != null)
                                    @if($step->done)
                                        {{$step->updated_at->format('d.m.Y H:i')}}
                                    @else
                                        {{$step->endDate->format('d.m.Y')}}
                                    @endif
                                @else
                                    {{$step->durationDays}} Tage nach Abschluss des letzten Schrittes
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
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
            </div>
        </div>
    </div>
        @if(count($step->childs)>0)
            <div class="row text-center">
                <div class="col-12">
                    <i class="fas fa-arrow-down"></i>
                </div>
            </div>
            <div class="row">
                @each('procedure.stepStarted',$step->childs, 'step')
            </div>
        @endif
</div>







