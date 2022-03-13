@extends('layouts.app')
@section('title') - Listen @endsection

@section('content')
    <div class="container-fluid">
        <div class="row justify-content-center">
                    <div class="col-md-12 col-sm-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="col-auto">
                                    aktuelle Listen
                                </h5>
                                @can('create Terminliste')
                                    <div class="col-auto pull-right">
                                        <a href="{{url('listen/create')}}" class="card-link">erstellen</a>
                                    </div>
                                @endcan
                            </div>
                            @if(count($listen)<1)
                                <div class="card-body alert-info">
                                    <p>
                                        Es wurden keine aktuellen Listen gefunden
                                    </p>
                                </div>
                            @endif
                        </div>
                        @if(count($listen)>=1)
                                    <div class="card-columns">
                                        @foreach($listen as $liste)
                                            <div class="card">
                                                <div class="card-header  @if($liste->active == 0) bg-info @endif ">
                                                    @if($liste->users_id == auth()->user()->id or auth()->user()->can('create Terminliste'))
                                                        <div class="d-inline pull-right">
                                                            <div class="pull-right">
                                                                <a href="{{url("listen/$liste->id/edit")}}" class="card-link">
                                                                    <i class="fas fa-pencil-alt @if($liste->active == 0) text-gray @endif" title="bearbeiten"></i>
                                                                </a>
                                                                @if($liste->active == 0)
                                                                    <a href="{{url("listen/$liste->id/activate")}}"
                                                                       class="card-link">
                                                                        <i class="fas fa-eye  @if($liste->active == 0) text-gray @endif"
                                                                           title="veröffentlichen"></i>
                                                                    </a>
                                                                @else
                                                                    <a href="{{url("listen/$liste->id/deactivate")}}"
                                                                       class="card-link">
                                                                        <i class="fas fa-eye-slash"
                                                                           title="ausblenden"></i>
                                                                    </a>
                                                                @endif
                                                                <a href="{{url("listen/$liste->id/archiv")}}"
                                                                   class="card-link">
                                                                    <i class="fa fa-archive"></i>
                                                                </a>
                                                            </div>

                                                        </div>
                                                    @endif
                                                    <h5>
                                                        {{$liste->listenname}} @if($liste->active == 0) (inaktiv) @endif


                                                    </h5>
                                                    <div class="row">
                                                        <div class="col-sm-8 col-md-8 col-lg-8">
                                                            <p class="info small">
                                                                {!! $liste->comment !!}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="row" id="collapse{{$liste->id}}">
                                                            @foreach($liste->groups as $group)
                                                                <div class="badge">
                                                                    {{$group->name}}@if(!$loop->last), @endif
                                                                </div>
                                                            @endforeach
                                                    </div>


                                                </div>
                                                <div class="card-body border-top">

                                                    @if($liste->eintragungen->where('reserviert_fuer', auth()->id())->first() != null)
                                                        @foreach($liste->eintragungen->where('reserviert_fuer', auth()->id())->sortBy('termin')->all() as $eintragung)
                                                            <div class="row">
                                                                <div class="col-8">
                                                                    <b>Ihr Termin:</b > <br>{{$eintragung->termin->format('d.m.Y H:i')}} Uhr
                                                                </div>
                                                                <div class="col-4">

                                                                    <form action="{{url('eintragungen/absagen/'.$eintragung->id)}}" method="post">
                                                                        @csrf
                                                                        @method("delete")
                                                                        <button type="submit" class="btn btn-xs btn-danger">absagen</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    @endif
                                                    @if($liste->eintragungen->where('reserviert_fuer', auth()->id())->count() < 1 or $liste->multiple == 1 or $liste->user_id == auth()->id() or auth()->user()->can('create Terminliste'))
                                                        <div class="row">
                                                            <a href="{{url("listen/$liste->id")}}" class="btn btn-primary btn-block">
                                                                Termine anzeigen
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="card-footer border-top">
                                                    <small>
                                                        endet: {{$liste->ende->format('d.m.Y')}}
                                                    </small>
                                                    @if(auth()->user()->can('create Terminliste'))
                                                        <div class="badge badge-info pull-right">
                                                            {{$liste->type}}
                                                        </div>
                                                    @endif
                                                </div>

                                            </div>
                                        @endforeach
                                    </div>
                        @endif
                    </div>
        </div>
    </div>
    @if(auth()->user()->can('create Terminliste'))
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        abgelaufene Listen
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th>
                                Titel
                            </th>
                            <th>
                                abgelaufen am
                            </th>
                            <th>
                                Aktionen
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($archiv as $liste)
                            <tr>
                                <td>
                                    {{$liste->listenname}}
                                </td>
                                <td>
                                    {{$liste->ende->format('d.m.Y')}}
                                </td>
                                <td>
                                    <a href="{{url("listen/$liste->id")}}" class="card-link">
                                        <i class="fa fa-eye"></i>
                                    </a>

                                    <a href="{{url("listen/$liste->id/refresh")}}" class="card-link">
                                        <i class="fas fa-redo"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr>
                            <td colspan="3">
                                {{$archiv->links()}}
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    @endif
@endsection
