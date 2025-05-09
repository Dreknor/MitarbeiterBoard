@extends('layouts.app')

@section('content')
    <div class="container-fluid">
    <div class="">
        @foreach($cards->groupBy('row') as $row => $cards)
            <div class="row">
                @foreach($cards->groupBy('col') as $col => $cards_col)
                    <div class="col-sm-12 col-md-{{12/floor($cards->groupBy('col')->count())}} mx-auto">
                        @foreach($cards_col->sortBy('col') as $card)
                            @include('dashboard.editLinks', ['card' => $card])
                            @include($card->view)
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

        <div class="row">
            <div class="col-auto">
                <a href="#" class="btn btn-outline-primary" id="editDashboardButton">
                    <i class="fa fa-edit"></i>
                    Ansicht bearbeiten
                </a>
            </div>
            <div class="col-auto d-none editLinks">
                Ausgeblendete Elemente:
                <ul class="list-group">
                    @foreach(auth()->user()->dashboardCards()->notActive()->order()->get() as $card)
                        <li class="list-group-item">
                            <a href="{{url('dashboard/'.$card->id.'/toggle')}}" class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-eye"></i>
                            </a>
                            {{$card->title}}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        $(document).ready(function(){
            $('#editDashboardButton').on('click', function(e){
                e.preventDefault();
                $('.editLinks').removeClass('d-none');
            });
        });
    </script>
    <script type="text/javascript">
        function disableCard (id) {
            $('#card_' + id).hide();
            console.log(id);

            $.ajax({
                url: '{{url('cards/disable')}}',
                method: 'post',
                data: {
                    id: id,
                    _token: '{{csrf_token()}}'
                }
            });
        }
    </script>
@endpush
