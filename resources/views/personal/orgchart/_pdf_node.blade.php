<div class="connector">
    <div class="node" @if($position->color) style="border-color: {{ $position->color }};" @endif>
        <div class="node-name">{{ $position->name }}</div>
        @foreach($position->currentUsers as $user)
        <div class="node-person">{{ $user->name }}</div>
        @endforeach
        @foreach($position->currentDeputy as $dep)
        <div class="node-deputy">Stv.: {{ $dep->name }}</div>
        @endforeach
        @if($position->currentUsers->isEmpty())
        <div class="node-empty">unbesetzt</div>
        @endif
    </div>

    @if($position->children->isNotEmpty())
    <div class="line-v"></div>
    <div class="level">
        @foreach($position->children as $child)
        @include('personal.orgchart._pdf_node', ['position' => $child, 'depth' => $depth + 1])
        @endforeach
    </div>
    @endif
</div>

