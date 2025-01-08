<?php

namespace App\Http\Controllers;

use App\Models\DashboardCard;
use App\Models\DashBoardUser;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function up(DashBoardUser $dashBoardUser)
    {
        $dashBoardUser->row = $dashBoardUser->row - 1;
        $dashBoardUser->save();

        return redirect()->back();
    }

    public function down(DashBoardUser $dashBoardUser)
    {
        $dashBoardUser->row = $dashBoardUser->row + 1;
        $dashBoardUser->save();

        return redirect()->back();
    }

    public function left(DashBoardUser $dashBoardUser)
    {
        $dashBoardUser->col = $dashBoardUser->col - 1;
        $dashBoardUser->save();

        return redirect()->back();
    }

    public function right(DashBoardUser $dashBoardUser)
    {
        $dashBoardUser->col = $dashBoardUser->col + 1;
        $dashBoardUser->save();

        return redirect()->back();
    }

    public function toggle(DashBoardUser $dashBoardUser)
    {
        $dashBoardUser->active = !$dashBoardUser->active;
        $dashBoardUser->save();

        return redirect()->back();
    }

    public function disableCard(Request $request){
        $card = DashBoardUser::where('id', $request->id)->first();
        if ($card and $card->user_id == auth()->id()){
            $card->active = false;
            $card->save();
        }

        return response()->json(['success' => true]);
    }
}
