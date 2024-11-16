<div class="row mt-2" id="card_{{$card->id}}">
    <div class="col-12">
        <div class="card">
            <div class="card-header text-white bg-gradient-directional-blue">
                <div class="pull-right ">
                    <a href="#" class="text-white btn btn-link" onclick="disableCard({{$card->id}})">
                        X
                    </a>
                </div>
                <h5 class="card-title">
                    freie Räume

                </h5>

            </div>
            <div class="card-body">
                @if($freeRooms and $freeRooms->count() > 0)
                    <div class="card-columns">
                        @foreach($freeRooms as $room)
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                            {{$room->name}}
                                    </div>
                                    <div class="card-footer bg-light">
                                        @if($room->nextBooking())
                                            <div class="text-warning">
                                                {{Carbon\Carbon::parse($room->nextBooking()->start)->diffForHumans()}}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                        @endforeach
                    </div>
                @else
                    <p>
                        Es stehen keine freien Räume zur Verfügung
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
