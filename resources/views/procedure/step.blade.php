<div class="col-auto">
    <div class="row ">
        <div class="col-12">
            <div class="card bg-light border" data-step="{{$step->id}}">
                <div class="card-header">
                    <h6>
                        {{$step->name}}
                        <div class="pull-right">
                            <small>
                                <a href="{{url('procedure/step/'.$step->id."/edit")}}">
                                    <i class="fas fa-pen"></i>
                                </a>
                            </small>
                        </div>
                    </h6>
                    <p class="small">
                        @if($step->parent != "")
                            nach: {{$step->parent_rel->name}}
                        @endif
                    </p>
                    <small>
                        {!! $step->description !!}
                    </small>
                </div>
                <div class="card-body">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-6">
                                <b>
                                    Verantwortlich:
                                </b>
                            </div>
                            <div class="col-6">
                                {{$step->position->name}}
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <b>
                                    Dauer:
                                </b>
                            </div>
                            <div class="col-6">
                                {{$step->durationDays}} Tage
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <div class="btn btn-sm btn-outline-success newStep" data-parent="{{$step->id}}"  data-target="#stepModal"  data-toggle="modal">
                        <i class="fas fa-plus" data-parent="{{$step->id}}"></i> <i class="fas fa-arrow-down" data-parent="{{$step->id}}"></i>
                    </div>
                    <div class="btn btn-sm btn-outline-info newStep" data-parent="{{$step->parent}}"  data-target="#stepModal"  data-toggle="modal">
                        <i class="fas fa-plus" data-parent="{{$step->parent}}"></i> <i class="fas fa-arrow-right" data-parent="{{$step->parent}}"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
        @if(count($step->childs)>0)
            <div class="row text-center mb-4">
                <div class="col-12">
                    <i class="fas fa-arrow-down"></i>
                </div>
            </div>
            <div class="row">
                @each('procedure.step',$step->childs, 'step')
            </div>
        @endif
</div>







