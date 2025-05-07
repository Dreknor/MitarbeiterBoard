    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>
                        Urlaubsantrag
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" action="{{url('holidays')}}" class="">
                        @csrf
                        <div class="form-row">
                            <div class="col-12">
                                <label for="employe_id">Mitarbeiter</label>
                                <select name="employe_id" id="employe_id" class="form-control">
                                    @can('approve holidays')
                                        <option value="all">Alle</option>
                                        @foreach($users as $user)
                                            <option value="{{$user->id}}">{{$user->name}}</option>
                                        @endforeach
                                    @else
                                        <option value="{{auth()->user()->id}}">{{auth()->user()->name}}</option>
                                    @endcan

                                </select>
                            </div>
                        </div>
                        <div class="form-row mt-2">
                            <div class="col-6">
                                <label for="start_date">Von</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" value="" required>
                            </div>
                            <div class="col-6">
                                <label for="end_date">Bis</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" value="" required>
                            </div>
                        </div>
                        <div class="form-row mt-2">
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Urlaub beantragen
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
