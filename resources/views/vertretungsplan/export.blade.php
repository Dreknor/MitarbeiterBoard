<div class="card">
    <div class="card-body">
        <form action="{{url('export/vertretungen')}}" method="post" class="form form-horizontal">
            @csrf
            <div class="form-row">
                <div class="col-sm-12 col-md-6">
                    <div class="form-group">
                        <label for="startDate">
                            Start - Zeitraum
                        </label>
                        <input type="date"  id="startDate" name="startDate" required class="form-control" value="{{old('startDate', config('config.schuljahresbeginn')->format('Y-m-d'))}}">
                    </div>
                </div>
                <div class="col-sm-12 col-md-6">
                    <div class="form-group">
                        <label for="endDate">
                            Ende - Zeitraum
                        </label>
                        <input type="date"  id="endDate" name="endDate" class="form-control" value="{{old('endDate', \Carbon\Carbon::now()->format('Y-m-d'))}}">
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="col-sm-12 col-md-6">
                    <a href="#" id="FilterButton" class="btn btn-bg-gradient-x-blue-purple-1 btn-block">
                        Zeitraum begrenzen
                    </a>
                </div>
                <div class="col-sm-12 col-md-6">
                    <button id="submit" type="submit" class="btn btn-primary btn-block">
                        Export als EXCEL
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('js')
    <script>
        $('#FilterButton').on('click', function () {
            var startdate  = $('#startDate').val();
            var enddate = $('#endDate').val();
            url = "{{url('vertretungen/archiv')}}" + '/' + startdate + '/' + enddate;
            window.location = url;
        })
    </script>
@endpush
