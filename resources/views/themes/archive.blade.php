@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Themen-Archiv</h5>
            </div>
            <div class="card-body border-top">
                @if (count($themes) == 0)

                    <p>
                        Es gibt keine archivierten Themen
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
                                <th>Datum</th>
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
                                        {{$theme->updated_at->format('d.m.Y')}}
                                    </td>
                                    <td>
                                        <a href="{{url("themes/$theme->id")}}">anzeigen</a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <p>{!! $themes->links() !!}</p>
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
