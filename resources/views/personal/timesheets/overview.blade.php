@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h5>
                    Übersicht für {{$user->name}}
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive-md">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th>
                                Monat
                            </th>
                            <th>
                                Stundenkonto
                            </th>
                            <th>
                                Urlaub
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($timesheets as $timesheet)
                            <tr>
                                <td>
                                    {{$timesheet->year}}/{{$timesheet->month}}
                                </td>
                                <td>
                                    {{convertTime($timesheet->working_time_account)}}
                                </td>
                                <td>
                                    {{$timesheet->holidays_rest}}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


    </div>

@endsection
