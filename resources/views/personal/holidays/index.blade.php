@extends('layouts.app')


@section('title')
    Urlaubsverwaltung
@endsection

@section('site-title')
    Urlaubsverwaltung
@endsection

@section('content')
        <div class="row">

            <div class="col-md-6">
                @include('personal.holidays.partials.group_filter')
                @include('personal.holidays.partials.holidays_request')
            </div>
            <div class="col-md-6">
                @include('personal.holidays.partials.holidays_overview')
            </div>
        </div>
        @can('approve holidays')
            <div class="container mt-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5>Ungeprüfte Urlaubsanträge</h5>
                        <span class="badge badge-warning"> {{ $unapproved->count() }} offen</span>
                    </div>
                    <div class="card-body p-2">
                        <table class="table table-hover table-bordered table-responsive-md">
                            <thead class="thead-light">
                            <tr>
                                <th>Name</th>
                                <th>Von</th>
                                <th>Bis</th>
                                <th>Tage</th>
                                <th>Status</th>
                                <th>Aktion</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($unapproved as $holiday)
                                <tr>
                                    <td>{{ $holiday->employe->name }}</td>
                                    <td>{{ $holiday->start_date->format('d.m.Y') }}</td>
                                    <td>{{ $holiday->end_date->format('d.m.Y') }}</td>
                                    <td>{{ $holiday->days }}</td>
                                    <td>
                                <span class="badge {{ $holiday->approved ? 'badge-success' : 'badge-warning' }}">
                                    {{ $holiday->approved ? 'genehmigt' : 'offen' }}
                                </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#approveModal-{{ $holiday->id }}">
                                            Bearbeiten
                                        </button>
                                        <!-- Modal -->
                                        <div class="modal fade" id="approveModal-{{ $holiday->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Urlaubsantrag bearbeiten</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form action="{{ url('holidays/' . $holiday->id) }}" method="post">
                                                            @csrf
                                                            @method('put')
                                                            <div class="form-group">
                                                                <label>Aktion wählen</label>
                                                                <select class="form-control" name="action" required>
                                                                    <option value="approved">Genehmigen</option>
                                                                    <option value="rejected">Ablehnen</option>
                                                                </select>
                                                            </div>
                                                            <button type="submit" class="btn btn-primary">Speichern</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center">Keine ungeprüften Anträge gefunden.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
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
