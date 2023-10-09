@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                @can('export absence')
                    <div class="pull-right ml-2">
                        <a href="{{url('absences/export')}}" class="card-link text-warning">
                            <i class="fa fa-file-export" title="Excel export"></i>
                            <div class="d-none d-md-block">
                                export
                            </div>
                        </a>
                    </div>
                @endcan
                <div class="pull-right ml-2">
                    <a href="{{url('absences/abo/daily')}}" class="card-link text-success">
                        @if(auth()->user()->absence_abo_daily != 1)
                            <i class="fa fa-bell" title="tägliche Zusammenfassung per E-Mail aktivieren"></i> <div class="d-none d-md-block">täglich</div>
                        @else
                            <i class="fa fa-bell-slash" title="tägliche Zusammenfassung per E-Mail deaktivieren"></i>
                            <div class="d-none d-md-block">
                                täglich
                            </div>
                        @endif
                    </a>
                </div>
                <div class="pull-right ml-2">
                    <a href="{{url('absences/abo/now')}}" class="card-link">
                        @if(auth()->user()->absence_abo_now != 1)
                            <i class="fa fa-bell" title="sofortige Benachrichtigung per E-Mail aktivieren"></i>
                            <div class="d-none d-md-block">
                                sofort
                            </div>
                        @else
                            <i class="fa fa-bell-slash" title="sofortige Benachrichtigung per E-Mail deaktivieren"></i>
                            <div class="d-none d-md-block">
                                sofort
                            </div>
                        @endif
                    </a>
                </div>
                <h6>
                    Abwesenheiten
                </h6>

            </div>
            <div class="card-body">
                @if(isset($absences) and $absences->count() > 0)
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th class="btn-link">
                                Name
                            </th>
                            <th class="btn-link">
                                Zeitraum
                            </th>
                            <th class="btn-link">
                                Grund
                            </th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($absences as $absence)
                            <tr>
                                <td>
                                    {{$absence->user->name}}
                                </td>
                                <td>
                                    @if($absence->showVertretungsplan)
                                        <i class="fas fa-columns text-info" title="Anzeige auf Vertretungsplan"></i>
                                    @endif
                                    {{$absence->start->format('d.m.Y')}} @if($absence->end->gt($absence->start))- {{$absence->end->format('d.m.Y')}}@endif
                                </td>
                                <td>
                                    {{$absence->reason}}
                                </td>
                                <td>
                                    @if(auth()->user()->can('delete absences') or $absence->creator_id == auth()->id())
                                        <a href="{{url('absences/'.$absence->id.'/delete')}}">
                                            <i class="fas fa-trash text-danger"></i>
                                        </a>

                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

                @else
                    Keine Abwesenheiten vorhanden
                @endif
            </div>
        </div>
    </div>

@endsection
@push('js')
    @push('js')
        <script src="//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
        <script>
            $(document).ready( function () {
                $('table').DataTable();
            } );
        </script>

    @endpush

    @section('css')
        <link href="//cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css" rel="stylesheet" />

    @endsection

@endpush
