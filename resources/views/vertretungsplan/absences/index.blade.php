@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>
                            Abwesenheiten
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-hover" id="absenceTable">
                            <thead>
                                <tr>
                                    <th>
                                        Name
                                    </th>
                                    <th>
                                        von
                                    </th>
                                    <th>
                                        bis
                                    </th>
                                    <th>
                                        Grund
                                    </th>
                                    <th>

                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($absences as $absence)
                                    <tr>
                                        <td>
                                            {{$absence->user->name}}
                                        </td>
                                        <td>
                                            {{$absence->start_date->format('d.m.Y')}}
                                        </td>
                                        <td>
                                            {{$absence->end_date->format('d.m.Y')}}
                                        </td>
                                        <td>
                                            {{$absence->reason}}
                                        </td>
                                        <td>
                                           <form action="{{url('vertretungsplan/abwesenheit/'.$absence->id.'/delete')}}" method="POST" class="form form-inline">
                                                  @csrf
                                                    @method('delete')
                                                  <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                  </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                        </table>

                    </div>
                    <div class="card-footer border-top">
                        <div class="row">
                            <div class="col-12">
                                <h6>
                                    neue Abwesenheit erfassen
                                </h6>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <form method="post" action="{{url('abwesenheiten')}}" class="form form-horizontal ">
                                    @csrf
                                    <div class="form-row  w-100">
                                        <label for="name">
                                            Lehrer
                                            <span class="text-danger">*</span>
                                        </label>
                                        <select name="user_id" class="custom-select">
                                            <option value="" readonly>Bitte wählen</option>
                                            @foreach($users as $user)
                                                <option value="{{$user->id}}">{{$user->name}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-row mt-2">
                                        <label for="start_date">
                                            von
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input type="date" name="start_date" class="form-control" required>
                                    </div>
                                    <div class="form-row mt-2">
                                        <label for="end_date text-danger">
                                            bis
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input type="date" name="end_date" class="form-control" required>
                                    </div>
                                    <div class="form-row mt-2">
                                        <label for="reason">
                                            Grund
                                        </label>
                                        <input type="text" name="reason" class="form-control" >
                                    </div>
                                    <div class="form-row mt-2">
                                        <button type="submit" class="btn btn-bg-gradient-x-blue-cyan btn-block">
                                            speichern
                                        </button>
                                    </div>


                                </form>
                            </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('js')
    <script src="//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.7/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.0.7/js/dataTables.bootstrap4.js"></script>
    <script>
        $(document).ready( function () {
            $('#absenceTable').DataTable();
        } );
    </script>

@endpush

@section('css')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.2/css/bootstrap.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/2.0.7/css/dataTables.bootstrap4.css" rel="stylesheet" />

@endsection
