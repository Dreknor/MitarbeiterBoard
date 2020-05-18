@extends('layouts.app')


@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    Suche
                </h5>
            </div>
            <div class="card-body">
                <div class="container-fluid">
                    <form method="post" class="form-inline">
                        <div class="form-row">
                            <input type="text" id="txtSearch" name="txtSearch" class="form-control"  placeholder="Search..." >
                        </div>

                    </form>
                </div>

            </div>
            <div class="card-body">
                <div id="result"></div>
            </div>
        </div>





    </div>

@endsection

@push('js')

    <script type="application/javascript">
        $(document).ready(function(){
            $('#txtSearch').on('keyup', function(){
                var text = $('#txtSearch').val();
                if (text.length > 3){
                    $.ajax({
                        type:"POST",
                        url: '{{url('search')}}',
                        data: {
                            'text': $('#txtSearch').val(),
                            '_token': "@csrf"
                        },
                        success: function(data) {
                            console.log(data);
                            $('#result').text(data);
                        }
                    });
                }
            });
        });
    </script>

@endpush
