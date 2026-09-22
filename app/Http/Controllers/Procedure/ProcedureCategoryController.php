<?php

namespace App\Http\Controllers\Procedure;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procedure\StoreProcedureCategoryRequest;
use App\Http\Requests\Procedure\UpdateProcedureCategoryRequest;
use App\Models\Procedure_Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Verwaltung der Prozess-Kategorien (§4.1 B-22/B-23/B-24).
 */
class ProcedureCategoryController extends Controller
{
    public function update(UpdateProcedureCategoryRequest $request, Procedure_Category $category): JsonResponse
    {
        $category->update($request->validated());
        Cache::forget('categories');

        return response()->json(['data' => $category->only('id', 'name', 'color')]);
    }

    public function destroy(Procedure_Category $category): JsonResponse
    {
        $user = auth()->user();
        if (!$user || (!$user->can('manage procedures') && !$user->can('manage procedure categories'))) {
            abort(403);
        }
        if ($category->procedures()->withTrashed()->count() > 0) {
            return response()->json([
                'message' => 'Kategorie kann nicht gelöscht werden, solange Prozesse zugeordnet sind.',
            ], 422);
        }

        $category->delete();
        Cache::forget('categories');

        return response()->json(['status' => 'ok']);
    }
}

