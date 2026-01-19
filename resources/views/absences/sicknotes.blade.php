@extends('layouts.app')

@section('content')
    <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                Abwesenheiten seit {{\Carbon\Carbon::now()->subYear()->format('d.m.Y')}}
                            </h5>
                            <p class="mb-0">
                                <b>Abwesenheitsgrund:</b> @foreach(config('absences.absence_sick_note') as $reason) {{$reason}}@if(!$loop->last),@endif @endforeach
                            </p>
                        </div>
                        <div>
                            <a href="{{url('sick_notes/export'.'?'.http_build_query(request()->except('page')))}}" class="btn btn-warning btn-sm" title="Gesamten Export herunterladen">
                                <i class="fa fa-file-excel"></i> Excel Export (Gesamt)
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Filter-Bereich -->
                <div class="card-body border-bottom">
                    <form method="GET" action="{{url('sick_notes')}}" class="row g-3">
                        <div class="col-md-3">
                            <label for="reason" class="form-label">Abwesenheitsgrund</label>
                            <select name="reason" id="reason" class="form-control form-control-sm">
                                <option value="">Alle Gründe</option>
                                @foreach($allReasons as $reason)
                                    <option value="{{$reason}}" {{$filterReason == $reason ? 'selected' : ''}}>{{$reason}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="user" class="form-label">Mitarbeiter</label>
                            <select name="user" id="user" class="form-control form-control-sm">
                                <option value="">Alle Mitarbeiter</option>
                                @foreach($allUsers as $user)
                                    <option value="{{$user->id}}" {{$filterUser == $user->id ? 'selected' : ''}}>{{$user->name}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="sick_note_status" class="form-label">Krankenschein-Status</label>
                            <select name="sick_note_status" id="sick_note_status" class="form-control form-control-sm">
                                <option value="">Alle Status</option>
                                <option value="with_note" {{$filterSickNoteStatus == 'with_note' ? 'selected' : ''}}>Mit Schein</option>
                                <option value="without_note" {{$filterSickNoteStatus == 'without_note' ? 'selected' : ''}}>Ohne Schein (nicht benötigt)</option>
                                <option value="missing_note" {{$filterSickNoteStatus == 'missing_note' ? 'selected' : ''}}>Schein fehlt</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm me-2">
                                <i class="fa fa-filter"></i> Filtern
                            </button>
                            <a href="{{url('sick_notes')}}" class="btn btn-secondary btn-sm">
                                <i class="fa fa-times"></i> Zurücksetzen
                            </a>
                        </div>
                    </form>
                </div>

                <div class="card-body">
                    <div class="table-responsive-md table-responsive-sm">
                        <table class="table table-full-width table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>
                                        <a href="?{{http_build_query(array_merge(request()->except(['sort_by', 'sort_order']), ['sort_by' => 'reason', 'sort_order' => $sortBy == 'reason' && $sortOrder == 'asc' ? 'desc' : 'asc']))}}" class="text-dark">
                                            Grund
                                            @if($sortBy == 'reason')
                                                <i class="fa fa-sort-{{$sortOrder == 'asc' ? 'up' : 'down'}}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>Name</th>
                                    <th>
                                        <a href="?{{http_build_query(array_merge(request()->except(['sort_by', 'sort_order']), ['sort_by' => 'start', 'sort_order' => $sortBy == 'start' && $sortOrder == 'asc' ? 'desc' : 'asc']))}}" class="text-dark">
                                            Von
                                            @if($sortBy == 'start')
                                                <i class="fa fa-sort-{{$sortOrder == 'asc' ? 'up' : 'down'}}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?{{http_build_query(array_merge(request()->except(['sort_by', 'sort_order']), ['sort_by' => 'end', 'sort_order' => $sortBy == 'end' && $sortOrder == 'asc' ? 'desc' : 'asc']))}}" class="text-dark">
                                            Bis
                                            @if($sortBy == 'end')
                                                <i class="fa fa-sort-{{$sortOrder == 'asc' ? 'up' : 'down'}}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?{{http_build_query(array_merge(request()->except(['sort_by', 'sort_order']), ['sort_by' => 'days', 'sort_order' => $sortBy == 'days' && $sortOrder == 'asc' ? 'desc' : 'asc']))}}" class="text-dark">
                                            Dauer
                                            @if($sortBy == 'days')
                                                <i class="fa fa-sort-{{$sortOrder == 'asc' ? 'up' : 'down'}}"></i>
                                            @endif
                                        </a>
                                    </th>
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
                                                @if($absence->sick_note_required or $absence->days >= settings('absence_sick_note_days', 'absences'))
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
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Mitarbeiter-Übersicht
                        </h5>
                    </div>
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
                                <th rowspan="2" class="border">
                                    Aktionen
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
                                    <td class="text-center">
                                        <a href="{{url('sick_notes/export/user/'.$user['user_id'])}}" class="btn btn-sm btn-outline-success" title="Excel Export für {{$user['user']}}">
                                            <i class="fa fa-file-excel"></i>
                                        </a>
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
