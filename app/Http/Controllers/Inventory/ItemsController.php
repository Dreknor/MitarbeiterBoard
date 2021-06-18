<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\createInventoryItemRequest;
use App\Http\Requests\editInventoryItemRequest;
use App\Models\Inventory\Category;
use App\Models\Inventory\Items;
use App\Models\Inventory\Lieferant;
use App\Models\Inventory\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

                    $originalFileName = $file->getClientOriginalName();
                    Storage::disk('upload')->put($originalFileName, file_get_contents($file));

                    $item
                        ->addMedia(Storage::disk('upload')->path($originalFileName))
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
     * Display the specified resource.
     *
     * @param  \App\Models\Inventory\Items  $item
     * @return \Illuminate\Http\Response
     */
    public function scan($uuid)
    {
        return view('inventory.items.scan', [
            'item' => Items::where('uuid', $uuid)->first(),
            'locations' => Location::all()
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Inventory\Items  $item
     * @return \Illuminate\Http\Response
     */
    public function edit($item)
    {
        $item = Items::where('id', $item)->first();

        return view('inventory.items.edit', [
            'item' => $item,
            'locations' => Location::all(),
            'categories' => Category::all(),
            'lieferanten' => Lieferant::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Inventory\Items  $item
     * @return \Illuminate\Http\Response
     */
    public function update(editInventoryItemRequest $request, Items $item)
    {
        $item->update($request->validated());
        if ($request->hasFile('files')){
            $files = $request->files->all();
            foreach ($files['files'] as $file) {
                $item
                    ->addMedia($file)
                    ->toMediaCollection();
            }
        }
        return redirect(url('inventory/items/'.$item->id))->with([
           'type'=>'success',
           'Meldung'=>'Daten wurden aktualisiert.'
        ]);
    }

    public function scanUpdate(Request $request, $uuid)
        {
            $item = Items::where('uuid', $uuid)->first();
            $item->update([
                'status' => $request->status,
                'location_id' => $request->location_id
            ]);

            return \view('inventory.items.scanSuccess');
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
