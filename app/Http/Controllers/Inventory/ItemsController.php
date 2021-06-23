<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\createInventoryItemRequest;
use App\Http\Requests\editInventoryItemRequest;
use App\Imports\InventoryItemsImport;
use App\Models\Inventory\Category;
use App\Models\Inventory\Items;
use App\Models\Inventory\Lieferant;
use App\Models\Inventory\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade as PDF;
use Maatwebsite\Excel\Facades\Excel;

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
    public function index(Request $request)
    {

        if ($request->search and $request->search != "") {
            $items = Items::where('name', 'LIKE' ,'%'.$request->search.'%')->orWhere('oldInvNumber', 'LIKE' , $request->search.'%')->orWhere('description', 'LIKE' , '%'.$request->search.'%')->with('category', 'location')->limit(150)->get();
        } else {
            $items = Items::orderBy('created_at', 'Desc')->with('category', 'location')->paginate(100);
        }

        return view('inventory.items.index',[
            'items' => $items,
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

        if ($request->has('number_indiv')){
            $y = 1;
            $number = $request->number;
        } else {
            $y = $request->number;
            $number = 1;
        }


        for ($x=0; $x < $y; $x++){
            $ownNumber = $request->oldInvNumber;
            $newNumber = $ownNumber;

            //Eigene Nummer auf Zahlen durchsuchen und das letzte Vorkommen um eines erhöhen, wenn dies nicht der erste Durchlauf ist
            if ($request->oldInvNumber != "" and $number == 1){
                preg_match_all('!\d+!', $request->oldInvNumber, $matches);

                if ($x > 0 and end($matches) != []){
                    $match = end($matches);
                        if (!is_array(end($match))){
                            $match= end($match);
                        }

                    $pos = strrpos($ownNumber, $match );
                    $lastNumber = $match + $x;
                    if($pos !== false)
                        {
                            $newNumber = substr_replace($ownNumber, $lastNumber , $pos, strlen($ownNumber));
                        }
                }
            }


            $item = new Items($request->validated());
            $item->oldInvNumber = $newNumber;
            $item->number = $number;
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
     * @return View
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
     * @return View
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
     * @return View
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
     * @return \Illuminate\Http\RedirectResponse
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
                'location_id' => $request->location_id,
                'number'    => $request->number ?: 1
            ]);

            $item->touch();
            return \view('inventory.items.scanSuccess');
        }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Inventory\Items  $inventoryItems
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($uuid)
    {
        Items::where('uuid', $uuid)->first()->delete();

        return redirect(url('inventory/items'))->with([
           'type'   => 'warning',
           'Meldung' => 'Gegenstand wurde entfernt'
        ]);
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


    public function showImport(){
        return view('inventory.items.import');
    }

    public function import (Request $request){
        if ($request->hasFile('file')){

            //dd($header);
            Excel::import(new InventoryItemsImport(), request()->file('file'));
            return redirect(url('inventory/items'));
        } else {
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Datei fehlt'
            ]);
        }

    }

}
