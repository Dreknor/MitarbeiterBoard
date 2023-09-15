@extends('layouts.app')

@section('content')
    <div class="container-fluid">
    <div class="">
        @foreach($cards->groupBy('row') as $row => $cards)
            <div class="row">
                @foreach($cards->sortBy('col') as $card)
                    <div class="col-sm-12 col-md-{{12/floor($cards->count())}} mx-auto">
                        @include('dashboard.editLinks', ['card' => $card])
                        @include($card->view)
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

@endpush
