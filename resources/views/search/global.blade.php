@extends('layouts.app')
@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header  bg-gradient-directional-blue-grey text-white">
                <h5 class="card-title">
                    Suche Global
                </h5>
            </div>
            <div class="card-body">
                <div class="container-fluid">

                        <div class="form-row">
                            <input type="text" id="txtSearch" name="txtSearch" class="form-control"  placeholder="Mindestens 4 Buchstaben eingeben ..." >
                        </div>

                </div>
            </div>
            <div class="card-body">
                <ul class="list-group" id="resultPosts">

                </ul>

            </div>
        </div>
    </div>
@endsection

@push('js')

    <script type="application/javascript">
        function padTo2Digits(num) {
            return num.toString().padStart(2, '0');
        }

        function formatDate(date) {
            return [
                padTo2Digits(date.getDate()),
                padTo2Digits(date.getMonth() + 1),
                date.getFullYear(),
            ].join('.');
        }

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
                                $("#resultPosts").empty().append('<li class="list-group-item">Keine Ergebnisse gefunden</li>')
                            } else
                            {
                                $("#resultPosts").empty();


                                for (var key in data) {
                                    var value = data[key];
                                    if (Object.keys(value).length > 0)
                                    {
                                        $("#resultPosts").append('<li class="list-group-item list-group-item-info">'+ key +'</li>')


                                        value.forEach(result => {
                                            if(key != "Nachrichten")
                                            {
                                                if (result != null){
                                                    var date = new Date(result.date)
                                                    $("#resultPosts").append('<li class="list-group-item "><a class="" href="{{url('/')}}'+'/' + key +'/themes/'+ result.id +'">'+ formatDate(date)  + ': '+ result.theme + '</a></li>')
                                                }

                                            } else {
                                                var date = new Date(result.created_at)
                                                $("#resultPosts").append('<li class="list-group-item">'+ result.header +' vom: '+ formatDate(date) +'</li>')
                                            }
                                        })
                                    }
                                    console.log(key, value);
                                }

                            }
                        }
                    });
                }
            });
        });
    </script>

@endpush
