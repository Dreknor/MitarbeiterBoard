<li>{{ $step->name }}</li>
@if (count($step->childs) > 0)
    <ul>
        @foreach($step->childs as $newStep)
            @include('procedure.recursice', $newStep)
        @endforeach
    </ul>
@endif
