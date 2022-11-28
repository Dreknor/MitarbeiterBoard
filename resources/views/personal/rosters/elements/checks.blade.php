<div class="card border border-left-0">
    <div class="card-header border-bottom">
        Checks
    </div>
    <div class="card-body">
        <ul class="list-group m-0 p-0">
            @foreach($checks[$day->format('Y-m-d')] as $name => $passed)
                <li @class(['list-group-item', 'border-0', "p-0"])>
                    @if($passed == "checked")
                        <div @class(['text-success'])>
                            <i class="fa fa-check-circle text-success"></i>
                            {{$name}}
                        </div>
                    @else
                        <div @class(['text-danger'])>
                            <i class="fa fa-circle"></i>
                            {{$name}}
                        </div>
                    @endif

                </li>
            @endforeach
        </ul>
    </div>
</div>
