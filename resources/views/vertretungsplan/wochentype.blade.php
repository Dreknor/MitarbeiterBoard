@extends('layouts.app')

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">
                A / B - Wochen
            </h5>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                <tr>
                    <th>
                        Woche
                    </th>
                    <th>
                        Typ
                    </th>
                    <th>
                    </th>
                </tr>
                </thead>
                <tbody>
                    @foreach($weeks as $week)
                        <tr>
                            <td>
                                {{$week->date}}
                            </td>
                            <td>
                                {{$week->type}}
                            </td>
                            <td>
                                <div class="row">
                                    <div class="col-6">
                                        <a href="{{url('weeks/change/'.$week->id)}}" class="btn btn-sm btn-info">
                                            <i class="fas fa-sync"></i>
                                        </a>
                                    </div>
                                    <div class="col-6 ">
                                        <form class="form-inline" method="post" action="{{url('weeks/delete/'.$week->id)}}">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class=" btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </div>



                            </td>
                        </tr>
                    @endforeach
                </tbody>

            </table>
        </div>
    </div>

@endsection
