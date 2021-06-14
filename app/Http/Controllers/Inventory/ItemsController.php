<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Category;
use App\Models\Inventory\Items;
use App\Models\Inventory\Location;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Inventory\Items  $inventoryItems
     * @return \Illuminate\Http\Response
     */
    public function show(Items $inventoryItems)
    {
        //
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
}
