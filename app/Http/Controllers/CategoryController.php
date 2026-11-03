<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCategoryRequest;
use App\Models\Procedure_Category;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:manage procedures');
    }

    public function store(CreateCategoryRequest $createCategoryRequest)
    {
        $category = new Procedure_Category($createCategoryRequest->validated());
        $category->save();

        return redirect()->back()->with([
           'type'=>'success',
           'Meldung'=>'Kategorie wurde erstellt',
        ]);
    }
}
