@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Themen</h5>
            </div>
            <div class="card-body">
                <a href="{{url('themes/create')}}" class="btn btn-block btn-primary">neues Thema</a>
            </div>
            <div class="card-body border-top">
                @if (count($themes) == 0)

                    <p>
                        Es gibt keine offenen Themen
                    </p>
                @else
                    <div class="table-responsive-sm table-responsive-md">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Von</th>
                                <th>Thema</th>
                                <th>Typ</th>
                                <th>Ziel</th>
                                <th>Dauer</th>
                                <th>Priorität</th>
                                <th>Informationen</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($themes as $theme)
                                <tr>
                                    <td>
                                        {{$theme->ersteller->name}}
                                    </td>
                                    <td>
                                        {{$theme->theme}}
                                    </td>

                                    <td>
                                        {{$theme->type->type}}
                                    </td>
                                    <td>
                                        {{$theme->goal}}
                                    </td>
                                    <td>
                                        {{$theme->duration}} Minuten
                                    </td>
                                    <td id="priority_{{$theme->id}}">
                                        @if ($theme->priorities->where('creator_id', auth()->id())->first())
                                            <div class="progress">
                                                <div class="progress-bar amount" role="progressbar" style="width: {{100-$theme->priority}}%;" ></div>
                                            </div>
                                        @else
                                            <input type="range" class="custom-range" id="theme_{{$theme->id}}" min="1" max="100" value="0" data-theme = "{{$theme->id}}">
                                        @endif
                                    </td>
                                    <td>
                                        @if ($theme->information != "")
                                            <a href="{{url("themes/$theme->id")}}">anzeigen</a>
                                        @else
                                            nicht vorhanden
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                @endif
            </div>
        </div>
    </div>
@stop

@push('js')
    <script>
        $('input[type=range]').on("change", function() {
            let theme = $(this).data('theme');
            let url = "{{url('themes')}}";
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

@push('css')

@endpush