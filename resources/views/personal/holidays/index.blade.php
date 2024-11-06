@extends('layouts.app')


@section('title')
    Urlaubsverwaltung
@endsection

@section('site-title')
    Urlaubsverwaltung
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
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
    @can('approve holidays')
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                            <h5>
                                ungeprüfte Urlaubsanträge
                            </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover border table-responsive-sm">
                            <thead>
                            <tr>
                                <th class="border-right">Name</th>
                                <th class="border-right">Von</th>
                                <th class="border-right">Bis</th>
                                <th class="border-right">Tage</th>
                                <th class="border-right">Status</th>
                                <th class="border-right">Aktion</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($unapproved as $holiday)
                                <tr>
                                    <td class="border-right w-25">
                                        {{$holiday->employe->name}}
                                    </td>
                                    <td class="border-right">
                                        {{$holiday->start_date->format('d.m.Y')}}
                                    </td>
                                    <td class="border-right">
                                        {{$holiday->end_date->format('d.m.Y')}}
                                    </td>
                                    <td class="border-right">
                                        {{$holiday->days}}
                                    </td>
                                    <td class="border-right">
                                        @if($holiday->approved)
                                            <span class="badge badge-success">genehmigt</span>
                                        @else
                                            <span class="badge badge-warning">offen</span>
                                        @endif
                                    </td>
                                    <td class="border-right">
                                        <form action="{{url('holidays/'.$holiday->id)}}" method="post">
                                            @csrf
                                            @method('put')
                                            <select class="custom-select" name="action">
                                                <option value="approved">genehmigen</option>
                                                <option value="rejected">ablehnen</option>
                                            </select>
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-check"></i> genehmigen
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endcan
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>
                        Urlaubsübersicht ({{$month->monthName}} {{$month->year}})
                    </h5>
                    <div class="pull-right">
                        <a href="{{url('holidays/export/'.$month->year)}}" class="btn btn-outline-primary" id="exportLink">
                            <i class="fas fa-file-pdf"></i> Export {{$month->year}}
                        </a>
                    </div>
                    <div class="row">
                        <div class="col-auto">
                            <a href="{{url('holidays/'.$month->month.'/'.$month->copy()->subYear()->year)}}" class="btn btn-outline-primary">
                                <i class="fas fa-chevron-left"></i><i class="fas fa-chevron-left"></i> {{$month->copy()->subYear()->monthName}} {{$month->copy()->subYear()->year}}
                            </a>
                        </div>
                        <div class="col-auto">
                            <a href="{{url('holidays/'.$month->copy()->subMonth()->month.'/'.$month->copy()->subMonth()->year)}}"  class="btn btn-outline-primary">
                                <i class="fas fa-chevron-left"></i> {{$month->copy()->subMonth()->monthName}} {{$month->copy()->subMonth()->year}}
                            </a>
                        </div>
                        <div class="col">

                        </div>
                        <div class="col-auto">
                            <a href="{{url('holidays/'.$month->copy()->addMonth()->month.'/'.$month->copy()->addMonth()->year)}}"  class="btn btn-outline-primary">
                                <i class="fas fa-chevron-right"></i> {{$month->copy()->addMonth()->monthName}} {{$month->copy()->addMonth()->year}}
                            </a>
                        </div>

                        <div class="col-auto">
                            <a href="{{url('holidays/'.$month->month.'/'.$month->copy()->addYear()->year)}}" class="btn btn-outline-primary">
                                <i class="fas fa-chevron-right"></i><i class="fas fa-chevron-right"></i> {{$month->copy()->addYear()->monthName}} {{$month->copy()->addYear()->year}}
                            </a>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <label for="group">Gruppe</label>
                            <select name="group" id="group_filter" class="form-control">
                                <option value="all">Alle</option>
                                @foreach(auth()->user()->groups_rel as $group)
                                    <option value="{{$group->name}}">{{$group->name}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="container-fluid">
                        @include('personal.holidays.partials.table', [
                            'holidays' => $holidays,
                            'startOfTable' => $month->copy(),
                            'endOfTable' => $month->copy()->addDays(15),
                            'users' => $users
                            ])
                        @include('personal.holidays.partials.table', [
                            'holidays' => $holidays,
                            'startOfTable' => $month->copy()->addDays(16),
                            'endOfTable' => $month->copy()->endOfMonth(),
                            'users' => $users
                            ])
                    </div>
                </div>

            </div>
        </div>

    </div>
@endsection

@push('js')
    <script>
        $('#group_filter').change(function() {
            document.cookie = "group="+$(this).val();
            var group = $(this).val();
            if(group == 'all') {
                $('table tbody tr').show();

                $('#exportLink').attr('href', '{{url('holidays/export/'.$month->year)}}');


            } else {
                $('table tbody tr').hide();
                $('table tbody tr.'+group).show();

                $('#exportLink').attr('href', '{{url('holidays/export/'.$month->year)}}'+'/'+group);
            }

            console.log($('#exportLink').attr('href'));
        });

        document.addEventListener('DOMContentLoaded', function() {
            var group = document.cookie.replace(/(?:(?:^|.*;\s*)group\s*\=\s*([^;]*).*$)|^.*$/, "$1");
            if(group == 'all') {
                $('#group_filter').val('all');
                $('table tbody tr').show();

                $('#exportLink').attr('href', '{{url('holidays/export/'.$month->year)}}');
            } else {
                $('#group_filter').val(group);
                $('table tbody tr').hide();
                $('table tbody tr.'+group).show();

                $('#exportLink').attr('href', '{{url('holidays/export/'.$month->year)}}'+'/'+group);

            }
        });
    </script>
@endpush
