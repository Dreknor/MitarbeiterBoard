<form>
    <div class="form-group">
        <label for="exampleFormControlInput1">Name:</label>
        <input type="text" class="form-control" id="exampleFormControlInput1" placeholder="Klassenname" wire:model="name">
        @error('name') <span class="text-danger">{{ $message }}</span>@enderror
    </div>
</form>
