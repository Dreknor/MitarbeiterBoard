<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCategoryRequest;
use App\Models\Procedure_Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function store(CreateCategoryRequest $createCategoryRequest)
    {
        $category = new Procedure_Category($createCategoryRequest->validated());
        $category->save();

        Cache::forget('categories');

        return redirect()->back()->with([
           'type'=>'success',
           'Meldung'=>'Kategorie wurde erstellt',
        ]);
    }
}
