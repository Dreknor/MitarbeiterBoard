<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12 col-md-7">
            <h5 class="card-title">Themen {{request()->segment(1)}}</h5>
        </div>
        <div class="col-sm-12 col-md-4 ">

                        <div class="col-6 pull-right">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-info dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fa fa-eye"></i>
                                    <div class="d-none d-lg-inline ">
                                        Ansicht ändern
                                    </div>
                                </button>
                                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                    <a class="dropdown-item" href="{{url(request()->segment(1).'/view/date')}}">
                                        @if($viewType == 'date') <i class="fas fa-check"></i> @endif
                                        Datum
                                    </a>
                                    <a class="dropdown-item" href="{{url(request()->segment(1).'/view/type')}}">
                                        @if($viewType == 'type') <i class="fas fa-check"></i> @endif
                                        Type
                                    </a>
                                    <a class="dropdown-item" href="{{url(request()->segment(1).'/view/priority')}}">
                                        @if($viewType == 'priority') <i class="fas fa-check"></i> @endif
                                        Priorität
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-5 pull-right">
                            @if(!isset($subscription))
                                <a href="{{url("subscription/group/".request()->segment(1))}}" class="btn btn-sm btn-outline-info">
                                    <i class="far fa-bell"></i>
                                </a>
                            @else
                                <a href="{{url("subscription/group/".request()->segment(1)."/remove")}}" class="btn btn-sm btn-info">
                                    <i class="fas fa-bell"></i>
                                </a>
                            @endif
                        </div>

        </div>


    </div>
</div>
