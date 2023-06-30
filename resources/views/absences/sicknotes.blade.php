@extends('layouts.app')

@section('content')
    <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h5>
                        Abwesenheiten seit {{\Carbon\Carbon::now()->subYear()->format('d.m.Y')}}
                    </h5>
                    <p>
                        <b>Abwesenheitsgrund:</b> @foreach(config('absences.absence_sick_note') as $reason) {{$reason}}@if(!$loop->last),@endif @endforeach
                    </p>
                </div>
                <div class="card-body">
                    <div class="table-responsive-md table-responsive-sm">
                        <table class="table table-full-width table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Grund</th>
                                    <th>Name</th>
                                    <th>Von</th>
                                    <th>Bis</th>
                                    <th>Dauer</th>
                                    <th>Krankenschein</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($absences as $absence)
                                    <tr class="">
                                        <td>
                                            {{$absence->reason}}
                                        </td>
                                        <td>
                                            {{$absence->user->name}}
                                        </td>
                                        <td>
                                            {{$absence->start->format('d.m.Y')}}
                                        </td>
                                        <td>
                                            {{$absence->end->format('d.m.Y')}}
                                        </td>
                                        <td>
                                            {{$absence->days}}
                                        </td>
                                        <td>
                                            @if(! is_null($absence->sick_note_date))
                                                <div class="text-success">
                                                    Krankenschein vom {{$absence->sick_note_date->format('d.m.Y')}}
                                                </div>
                                            @else
                                                @if($absence->sick_note_required or $absence->days >= config('absences.absence_sick_note_days'))
                                                    <div class="text-danger">
                                                        Krankenschein benötigt
                                                    </div>
                                                @endif
                                            @endif
                                        </td>
                                        <td>
                                            <div class="row">
                                                @if(is_null($absence->sick_note_date))
                                                    <div class="col-auto">
                                                        <a href="{{url('sick_notes/'.$absence->id.'/set_note_date')}}" class="border rounded-circle border-success p-1 text-success" title="Krankenschein erfassen">
                                                            <i class="fa fa-check"></i>
                                                        </a>
                                                    </div>
                                                @endif
                                                @if(!is_null($absence->sick_note_date))
                                                    <div class="col-auto">
                                                        <a href="{{url('sick_notes/'.$absence->id.'/sick_note_remove')}}" class="border rounded-circle border-danger p-1 text-danger" title="Krankenschein entfernen">
                                                            <i class="fa fa-trash"></i>
                                                        </a>
                                                    </div>
                                                @endif
                                            </div>

                    </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    </div>
    <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h5>
                        Mitarbeiter-Übersicht
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive-md table-responsive-sm">
                        <table class="table table-striped">
                            <thead>
                            <tr class="text-center">
                                <th rowspan="2" class="border">
                                    Mitarbeiter
                                </th>
                                <th colspan="3" class="border">
                                    Tage
                                </th>
                            </tr>
                            <tr class="text-center">
                                <th class="border" >
                                    mit Schein
                                </th>
                                <th class="border">
                                    ohne Schein
                                </th>
                                <th class="border">
                                    fehlt
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($users as $user)
                                <tr class="text-center">
                                    <td>
                                        {{$user['user']}}
                                    </td>
                                    <td  class="text-center">
                                        {{$user['with_note']}}
                                    </td>
                                    <td  class="text-center">
                                        {{$user['without_note']}}
                                    </td>
                                    <td  class="text-center">
                                        {{$user['missing_note']}}
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
