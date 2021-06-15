<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\createInventoryLieferantenRequest;
use App\Http\Requests\editInventoryLieferantenRequest;
use App\Models\Inventory\Lieferant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LieferantController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:edit inventar');
    }
    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {
        return  view('inventory.lieferanten.index', [
            'lieferanten' => Lieferant::all()
        ]);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function create()
    {
        return  view('inventory.lieferanten.create', [

        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(createInventoryLieferantenRequest $request)
    {
        $lieferant = new Lieferant($request->validated());
        $lieferant->save();

        return redirect(url('inventory/lieferanten'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Inventory\Lieferant  $lieferant
     * @return View
     */
    public function edit( $id)
    {
        return  view('inventory.lieferanten.edit', [
            'lieferant' => Lieferant::find($id)
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param    $lieferant
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(editInventoryLieferantenRequest $request, $lieferant)
    {
        $lieferant = Lieferant::find($lieferant);
        $lieferant->update($request->validated());

        return redirect(url('inventory/lieferanten'))->with([
            'type' => 'success',
            'Meldung' => 'Lieferant wurde aktualisiert'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Inventory\Lieferant  $lieferant
     * @return \Illuminate\Http\Response
     */
    public function destroy(Lieferant $lieferant)
    {
        #ToDo
    }
}
