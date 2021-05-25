<?php

namespace App\Http\Controllers;

use App\Http\Requests\createTypeRequest;
use App\Models\Type;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;


class TypController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return View | RedirectResponse
     */
    public function index(Request $request)
    {
        if (!$request->user()->can('create types')){
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung'=>'Rechte fehlen'
            ]);
        }

        return view('themeTypes.index', [
            'typen'=>Type::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(createTypeRequest $request)
    {
        #Todo Folgen von needsProtocol
        $Typ = new Type($request->validated());
        $Typ->save();

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Der Typ '.$Typ->type.' steht nun zur Verfügung'
        ]);
    }

    #Todo Update or delete Types
}
