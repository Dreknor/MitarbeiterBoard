<div class="form-group">
    <label>Für welche Gruppen?</label>
    <br>


    @foreach(auth()->user()->groups() as $gruppe)
        <div>
            <input type="checkbox" id="{{$gruppe->name}}" name="gruppen[]" value="{{$gruppe->id}}">
            <label for="{{$gruppe->name}}">{{$gruppe->name}}</label>
        </div>
    @endforeach
</div>
