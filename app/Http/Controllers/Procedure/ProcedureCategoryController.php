<?php

namespace App\Http\Controllers\Procedure;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procedure\StoreProcedureCategoryRequest;
use App\Http\Requests\Procedure\UpdateProcedureCategoryRequest;
use App\Models\Procedure_Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Verwaltung der Prozess-Kategorien (§4.1 B-22/B-23/B-24).
 */
class ProcedureCategoryController extends Controller
{
    public function update(UpdateProcedureCategoryRequest $request, Procedure_Category $category): JsonResponse
    {
        $category->update($request->validated());
        return response()->json(['data' => $category->only('id', 'name', 'color')]);
    }

    public function destroy(Request $request, Procedure_Category $category): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        if (!$user || (!$user->can('manage procedures') && !$user->can('manage procedure categories'))) {
            abort(403);
        }

        if ($category->procedures()->withTrashed()->exists()) {
            $message = 'Kategorie kann nicht gelöscht werden, solange Prozesse zugeordnet sind.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : redirect()->back()->with(['type' => 'danger', 'Meldung' => $message]);
        }

        $category->delete();

        // Das Formular in der Vorlagen-Übersicht sendet klassisch (kein fetch) → Redirect statt JSON.
        return $request->expectsJson()
            ? response()->json(['status' => 'ok'])
            : redirect()->back()->with(['type' => 'warning', 'Meldung' => 'Kategorie wurde gelöscht.']);
    }
}
