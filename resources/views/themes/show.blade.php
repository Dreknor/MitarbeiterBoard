@extends('layouts.app')

@section('content')
    <div class="container-fluid">
            <p>
                <a href="{{url('themes')}}" class="btn btn-primary btn-link">zurück</a>
                @if ($theme->creator_id == auth()->id() and !$theme->completed)
                    <a href="{{url("themes/$theme->id/edit")}}" class="btn btn-warning btn-link pull-right">bearbeiten</a>
                @endif
            </p>


        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    {{$theme->theme}}
                </h5>
            </div>
            <div class="card-body border-top">
                <div class="row p-2">
                    <div class="col-sm-12 col-md-12 col-lg-3">
                        <b>
                            Priorität
                        </b>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-9">
                        @if ($theme->priorities->where('creator_id', auth()->id())->first())
                            <div class="progress">
                                <div class="progress-bar amount" role="progressbar" style="width: {{100-$theme->priority}}%;" ></div>
                            </div>
                        @else
                            <input type="range" class="custom-range" id="theme_{{$theme->id}}" min="1" max="100" value="0" data-theme = "{{$theme->id}}">
                        @endif
                    </div>
                </div>
                <div class="row p-2">
                    <div class="col-sm-12 col-md-12 col-lg-3">
                        <b>
                            Von
                        </b>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-9">
                        {{$theme->ersteller->name}}
                    </div>
                </div>
                <div class="row p-2">
                    <div class="col-sm-12 col-md-12 col-lg-3">
                        <b>
                            Typ
                        </b>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-9">
                        {{$theme->type->type}}
                    </div>
                </div>
                <div class="row p-2">
                    <div class="col-sm-12 col-md-12 col-lg-3">
                        <b>
                            Erstellt
                        </b>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-9">
                        {{$theme->created_at->format('d.m.Y H:i')}} Uhr
                    </div>
                </div>
                <div class="row p-2">
                    <div class="col-sm-12 col-md-12 col-lg-3">
                        <b>
                            Dauer
                        </b>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-9">
                        {{$theme->duration}} Minuten
                    </div>
                </div>
                <div class="row p-2">
                    <div class="col-sm-12 col-md-12 col-lg-3">
                        <b>
                            Informationen
                        </b>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-9">
                        {!! $theme->information !!}
                    </div>
                </div>
                <div class="row p-2">
                    <div class="col-sm-12 col-md-12 col-lg-3">
                        <b>
                            Protokoll
                        </b>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-9">
                        @if ($theme->protocols->count() == 0 )
                            <p>
                                Kein Protokoll vorhanden
                            </p>
                        @else
                            <ul class="list-group">
                                @foreach($theme->protocols as $protocol)
                                    <li class="list-group-item">
                                        {{$protocol->created_at->format('d.m.Y H:i')}} - {{$protocol->ersteller->name}}
                                        {!! $protocol->protocol !!}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
                <div class="row p-2">
                    <div class="col-12">
                       @if (!$theme->completed)
                           <a href="{{url('protocols/'.$theme->id)}}" class="btn btn-primary btn-block">Protokoll anlegen</a>
                       @endif
                    </div>
                </div>
                
            </div>
        </div>
    </div>
@stop

@push('js')
    <script>
        $('input[type=range]').on("change", function() {
            let theme = $(this).data('theme');
            let url = "{{url('themes/'.$theme->id)}}";
            $.ajax({
                type: "POST",
                url: '{{url('priorities')}}',
                data: {
                    "priority": $(this).val(),
                    'theme': theme,
                    "_token": "{{ csrf_token() }}",
                },
                success: function(responseText){
                    window.location.replace(url);
                }
            });
        });
    </script>
@endpush
