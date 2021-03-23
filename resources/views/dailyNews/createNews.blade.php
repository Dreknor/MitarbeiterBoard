<div class="card">
    <div class="card-body">
        <form action="{{url('dailyNews')}}" method="post" class="form form-inline">
            @csrf
                <div class="col-auto">
                    <div class="form-group">
                        <label for="date_start">
                            Datum (ab)*
                        </label>
                        <input type="date" min="{{\Carbon\Carbon::today()->format('Y-m-d')}}" id="date_start" name="date_start" required class="form-control" value="{{old('date_start', \Carbon\Carbon::tomorrow()->format('Y-m-d'))}}">
                    </div>
                </div>
                <div class="col-auto">
                    <div class="form-group">
                        <label for="date_end">
                            Datum (bis)
                        </label>
                        <input type="date" min="{{\Carbon\Carbon::tomorrow()}}" id="date_end" name="date_end" class="form-control" value="{{old('date_end')}}">
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label for="news">
                            News*
                        </label>
                        <input type="text" id="news" name="news" class="form-control col-10" value="{{old('news')}}">
                    </div>
                </div>
                <div class="col-auto">
                    <label for="submit">
                    </label>
                    <button id="submit" type="submit" class="btn btn-primary btn-block">
                        speichern
                    </button>
                </div>
        </form>
    </div>
</div>
