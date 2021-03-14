<form action="{{url('klassen')}}" method="post">
    @csrf
    <div class="form-group">
        <input type="text" class="form-control" placeholder="Klassenname" name="name" required>
        <button type="submit" class="btn btn-success">anlegen</button>
    </div>
</form>
