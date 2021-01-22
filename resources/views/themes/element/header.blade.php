<div class="container-fluid">
    <div class="row">
        <div class="col-8">
            <h5 class="card-title">Themen {{request()->segment(1)}}</h5>
        </div>
        <div class="col-4 ">
            <div class="pull-right">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-info dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Ansicht ändern
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
        </div>
    </div>
</div>
