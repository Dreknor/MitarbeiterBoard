<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCategoryRequest;
use App\Http\Requests\CreatePositionRequest;
use App\Models\Positions;
use App\Models\Procedure_Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PositionsController extends Controller
{
    public function store (CreatePositionRequest  $request){

        $position = new Positions($request->validated());
        $position->save();

        Cache::forget('positions');

        return redirect()->back()->with([
            'type'=>"success",
            "Meldung"=>"Position wurde erstellt"
        ]);
    }

    public function index(){

        $positions = Cache::remember('positions', 60*60, function (){
            return Positions::all();
        });

        return view('procedure.index',[
           'positions'=>$positions,
            'users'=>User::all()
        ]);
    }

    public function addUser(Request $request, Positions $position){

        $user = User::where('id',$request->input('person_id'))->first();

        if (!$position->users->contains($user)){
            $position->users()->attach($user);
        }


        return redirect()->back()->with([
            'type'=>"success",
            "Meldung"=>"Position wurde besetzt"
        ]);
    }

    public function removeUser(Positions $positions, User $users){
        $positions->users()->detach($users);

        return redirect()->back()->with([
            'type'=>"success",
            "Meldung"=>"Benutzer von Position entfernt"
        ]);
    }
}
