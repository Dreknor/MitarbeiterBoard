<?php

namespace App\View\Composers;

use App\Models\Room;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RoomsComposer
{
    /**
     *
     */
    public function __construct()
    {

    }

    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $rooms = Cache::remember('rooms', 60, function (){
            return Room::all();
        });

        $freeRooms = $rooms->filter(function ($room){
            return $room->availability == true;
        });
        $view->with('freeRooms', $freeRooms);
    }
}
