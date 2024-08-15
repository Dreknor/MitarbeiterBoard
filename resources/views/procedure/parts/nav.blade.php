<div class="card-header">
    <div class="container-fluid">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a href="{{url('procedure')}}" class="nav-link @if(request()->segment(1) == "procedure" and request()->segment(2) == "") active @endif">
                    <h5>aktive Prozesse </h5>
                </a>

            </li>

            <li class="nav-item">
                <a href="{{url('procedure/template')}}" class="nav-link @if(request()->segment(1) == "procedure" and request()->segment(2) == "template") active @endif">
                    <h5>
                        Vorlagen
                    </h5>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{url('procedure/positions')}}" class="nav-link @if(request()->segment(1) == "procedure" and request()->segment(2) == "positions") active @endif">
                    <h5>
                        Positionen
                    </h5>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{url('procedure/recurring')}}" class="nav-link @if(request()->segment(1) == "procedure" and request()->segment(2) == "recurring") active @endif">
                    <h5>
                        Wiederkehrende Prozesse
                    </h5>
                </a>
            </li>


        </ul>
    </div>
</div>
