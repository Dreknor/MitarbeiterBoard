<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\createInventoryCategoryRequest;
use App\Http\Requests\editInventoryCategoryRequest;
use App\Models\Inventory\Category;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
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
        return  view('inventory.categories.index', [
            'categories' => Category::all()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function create()
    {

        return  view('inventory.categories.create', [
            'categories' => Category::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(createInventoryCategoryRequest $request)
    {
        $category = new Category($request->validated());
        $category->save();

        return redirect(url('inventory/categories'))->with([
            'type' => 'success',
            'Meldung'=>'Kategorie wurde angelegt'
        ]);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return View
     */
    public function edit($id)
    {


        return  view('inventory.categories.edit', [
            'categories' => Category::all(),
            'category' => Category::find($id)
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(editInventoryCategoryRequest $request, $id)
    {
        $category = Category::find($id);


        $category->update([
            'name' => $request->name,
            'parent_id' => optional($request)->parent_id
        ]);

        return redirect(url('inventory/categories'))->with([
            'type' => 'success',
            'Meldung'=>'Änderungen gespeichert.'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        #ToDo
    }
}
