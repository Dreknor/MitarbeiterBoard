<div class="card">
    <div class="card-header text-white bg-gradient-directional-blue">
        <h5>
            {{$card->title}}
        </h5>
    </div>
    @can('approve holidays')
        <div class="card-body">
            <div class="container-fluid">
                <ul class="list-group">
                    @foreach($unapproved as $holiday)
                        <li class="list-group-item">
                            @if($holiday->employe->id != auth()->id())
                                <div class="row p-1 mb-1">
                                    <div class="col-auto ">
                                        {{$holiday->employe->name}}
                                    </div>

                                    <div class="col-auto">
                                        {{$holiday->start_date->format('d.m.Y')}} - {{$holiday->end_date->format('d.m.Y')}}
                                    </div>
                                </div>
                                @can('approve holidays')
                                    <div class="row border-top">
                                            <div class="col-12 p-auto">
                                                    <form action="{{url('holidays/'.$holiday->id)}}" method="post">
                                                        @csrf
                                                        @method('put')
                                                        <button type="submit" class="btn-link">
                                                            <i class="fas fa-check"></i> genehmigen
                                                        </button>
                                                    </form>
                                            </div>
                                        </div>
                                @endcan
                            @endif
                        </li>
                    @endforeach
                </ul>

            </div>
        </div>

    @endcan
    <div class="card-body">
        <div class="container-fluid">
            <ul class="list-group">

                @foreach($holidays as $holiday)
                    <li class="list-group-item @if($holiday->approved) list-group-item-success @else list-group-item-warning @endif">
                        <div class="row p-1 mb-1">
                            {{$holiday->start_date->format('d.m.Y')}} - {{$holiday->end_date->format('d.m.Y')}}
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="card-footer">
        <a href="{{url('holidays')}}" class="btn btn-primary">
            Urlaub beantragen
        </a>
    </div>
</div>
