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
                                <a href="{{url('weeks/change/'.$week->id)}}" class="">
                                    <i class="fas fa-sync"></i>
                                </a>

                            </td>
                        </tr>
                    @endforeach
                </tbody>

            </table>
        </div>
    </div>

@endsection
