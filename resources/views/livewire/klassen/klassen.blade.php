<div>

    @if (session()->has('message'))
        <div class="alert alert-success">
            {{ session('message') }}
        </div>
    @endif

    @if($updateMode)
        @include('livewire.klassen.update')
    @else
        @include('livewire.klassen.create')
    @endif

    <table class="table table-bordered mt-5">
        <thead>
        <tr>
            <th>Klasse</th>
            <th width="150px">Action</th>
        </tr>
        </thead>
        <tbody>
        @foreach($klassen as $klasse)
            <tr>
                <td>{{ $klasse->name }}</td>
                <td>
                    <button wire:click="edit({{ $klasse->id }})" class="btn btn-primary btn-sm">Edit</button>
                    <button wire:click="delete({{ $klasse->id }})" class="btn btn-danger btn-sm">Delete</button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
