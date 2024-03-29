@extends('layouts.app')
@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header  bg-gradient-directional-blue-grey text-white">
                <h5 class="card-title">
                    Suche @if(request()->segment(1) != 'search') in {{request()->segment(1)}} @else global @endif
                </h5>
                <p class="text-white">
                    ... in Themen, Zielen und Informationen und in Protokollen.
                </p>
                <p class="text-white">
                   Das Ergebnis sind immer die Themen.
                </p>
            </div>
            <div class="card-body">
                <div class="container-fluid">

                        <div class="form-row">
                            <input type="text" id="txtSearch" name="txtSearch" class="form-control"  placeholder="Mindestens 4 Buchstaben eingeben ..." >
                        </div>

                </div>
            </div>
            <div class="card-body">
                <ul class="list-group" id="result">

                </ul>

            </div>
        </div>
    </div>
@endsection

@push('js')

    <script type="application/javascript">
        $(document).ready(function(){
            $('#txtSearch').on('keyup', function(){
                var text = $(this).val();
                if (text.length > 3){
                    $.ajax({
                        type:"POST",
                        url: '{{url(request()->segment(1).'/search')}}',
                        data: {
                            'text': $('#txtSearch').val(),
                            '_token': "{{ csrf_token() }}"
                        },
                        success: function(data) {

                            if (Object.keys(data).length == 0)
                            {
                                $("#result").empty().append('<li class="list-group-item">Keine Ergebnisse gefunden</li>')
                            } else
                            {
                                $("#result").empty();
                                data.forEach(result =>
                                {
                                    if (result != null){

                                        $("#result").append('<li class="list-group-item"><a href="{{url(request()->segment(1).'/themes')}}/'+ result.id +'">'+result.date.substring(0,10) + ': '+ result.theme + '</a></li>')
                                    }
                                   });
                            }


                        }
                    });
                }
            });
        });
    </script>

@endpush
