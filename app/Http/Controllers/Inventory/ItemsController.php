<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\createInventoryItemRequest;
use App\Models\Inventory\Category;
use App\Models\Inventory\Items;
use App\Models\Inventory\Lieferant;
use App\Models\Inventory\Location;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Ramsey\Uuid\Uuid;
use Barryvdh\DomPDF\Facade as PDF;

class ItemsController extends Controller
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
        return view('inventory.items.index',[
            'items' => Items::all(),
            'locations' => Location::count(),
            'categories' => Category::count(),
            'lieferanten' => Lieferant::count(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function create()
    {

        return view('inventory.items.create',[
            'locations' => Location::all(),
            'categories' => Category::all(),
            'lieferanten' => Lieferant::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(createInventoryItemRequest $request)
    {
        for ($x=0; $x < $request->number; $x++){
            $item = new Items($request->validated());
            $item->uuid = uuid_create();
            $item->save();
            if ($request->hasFile('files')){
                $files = $request->files->all();
                foreach ($files['files'] as $file) {
                    $item
                        ->addMedia($file)
                        ->toMediaCollection();
                }
            }
        }

        return redirect(url('inventory/items'))->with([
            'type'  => 'success',
            'Meldung' => 'Items wurden angelegt.'
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Inventory\Items  $item
     * @return \Illuminate\Http\Response
     */
    public function show(Items $item)
    {
        return view('inventory.items.show', [
            'item' => $item
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Inventory\Items  $inventoryItems
     * @return \Illuminate\Http\Response
     */
    public function edit(Items $inventoryItems)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Inventory\Items  $inventoryItems
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Items $inventoryItems)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Inventory\Items  $inventoryItems
     * @return \Illuminate\Http\Response
     */
    public function destroy(Items $inventoryItems)
    {
        //
    }

    public function print(Request $request){

        $items = Items::whereIn('uuid',$request->selected)->get();

        $pdf = PDF::loadView('inventory.pdf.etikett', [
            'items'=>$items,
            'reihe' => $request->reihe,
            'spalte' => $request->spalte,
        ]);
        $pdf->setPaper('A4');

        return $pdf->stream();
    }
}
