<div class="card">
    <div class="card-body">
        <form action="{{url('export/vertretungen')}}" method="post" class="form form-horizontal">
            @csrf
            <div class="form-row">
                <div class="col-sm-12 col-md-6 col-lg-auto">
                    <div class="form-group">
                        <label for="startDate">
                            Start - Zeitraum
                        </label>
                        <input type="date"  id="startDate" name="startDate" required class="form-control" value="{{old('startDate', config('config.schuljahresbeginn')->format('Y-m-d'))}}">
                    </div>
                </div>
                <div class="col-sm-12 col-md-6 col-lg-auto">
                    <div class="form-group">
                        <label for="endDate">
                            Ende - Zeitraum
                        </label>
                        <input type="date"  id="endDate" name="endDate" class="form-control" value="{{old('endDate', \Carbon\Carbon::now())}}">
                    </div>
                </div>
                <div class="col-sm-12 col-md-3 col-lg-auto">
                    <label for="submit">

                    </label>
                    <button id="submit" type="submit" class="btn btn-primary btn-block">
                        Export
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
