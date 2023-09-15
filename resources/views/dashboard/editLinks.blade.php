<div id="editLinks_{{$card->id}}" class="card-header editLinks d-none bg-gradient-directional-white">
    <div class="row">
        <div class="col-auto">
            @if($card->row > 1)
                <a href="{{url('dashboard/'.$card->id.'/up')}}" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-arrow-alt-circle-up"></i>
                </a>
            @endif
            <a href="{{url('dashboard/'.$card->id.'/down')}}" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-arrow-alt-circle-down"></i>
            </a>
            @if($card->col > 1)
                <a href="{{url('dashboard/'.$card->id.'/left')}}" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-arrow-alt-circle-left"></i>
                </a>
            @endif
            @if($card->col < 12)
                <a href="{{url('dashboard/'.$card->id.'/right')}}" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-arrow-alt-circle-right"></i>
                </a>
            @endif

            <a href="{{url('dashboard/'.$card->id.'/toggle')}}" class="btn btn-sm btn-outline-danger">
                <i class="fa fa-eye-slash"></i>
            </a>
        </div>
        <div class="col-auto">
            Poition: {{$card->row}}/{{$card->col}}
        </div>
    </div>


</div>
