<?php

namespace App\Http\Controllers;

use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaedDiaryCategoryController extends Controller
{
    // ── Verwaltungs-View ─────────────────────────────────────────────────────

    /**
     * GET /paed-diary/categories/manage
     * Rendert den Verwaltungs-View mit Tab "Notizkategorien" und Tab "Spaltengruppen".
     */
    public function manageView()
    {
        $canManageGlobal = Auth::user()->can('manage global paed diary categories');
        return view('paedDiary.categories.index', compact('canManageGlobal'));
    }

    // ── Eigene Kategorien ─────────────────────────────────────────────────────

    /**
     * GET /paed-diary/categories
     * JSON: Alle Kategorien (global + eigene des Users).
     */
    public function getCategories()
    {
        $user            = Auth::user();
        $canManageGlobal = $user->can('manage global paed diary categories');

        $categories = PaedDiaryCategory::forUser($user->id)
            ->orderByRaw('user_id IS NULL DESC')   // global zuerst
            ->orderBy('name')
            ->get(['id', 'name', 'user_id']);

        return response()->json([
            'categories' => $categories->map(fn ($c) => [
                'id'         => $c->id,
                'name'       => $c->name,
                'is_global'  => $c->isGlobal(),
                'can_edit'   => $c->isGlobal() ? $canManageGlobal : $c->isOwnedBy($user->id),
                'can_delete' => $c->isGlobal() ? $canManageGlobal : $c->isOwnedBy($user->id),
            ]),
        ]);
    }

    /**
     * POST /paed-diary/categories
     * Eigene Kategorie erstellen.
     */
    public function storeCategory(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $name = trim($data['name']);

        // Duplikat prüfen (global oder eigene)
        if (PaedDiaryCategory::forUser($user->id)->where('name', $name)->exists()) {
            return response()->json(['message' => 'Kategorie mit diesem Namen existiert bereits'], 422);
        }

        $category = PaedDiaryCategory::create([
            'name'    => $name,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success'  => true,
            'category' => [
                'id'         => $category->id,
                'name'       => $category->name,
                'is_global'  => false,
                'can_edit'   => true,
                'can_delete' => true,
            ],
        ]);
    }

    /**
     * PUT /paed-diary/categories/{category}/rename
     * Eigene Kategorie umbenennen.
     */
    public function renameCategory(PaedDiaryCategory $category, Request $request)
    {
        $user = Auth::user();

        if (! $category->isOwnedBy($user->id)) {
            return response()->json(['message' => 'Keine Berechtigung'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $newName = trim($data['name']);

        $exists = PaedDiaryCategory::where('name', $newName)
            ->where('user_id', $user->id)
            ->where('id', '!=', $category->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Kategorie mit diesem Namen existiert bereits'], 422);
        }

        $category->name = $newName;
        $category->save();

        return response()->json(['success' => true, 'name' => $category->name]);
    }

    /**
     * DELETE /paed-diary/categories/{category}
     * Eigene Kategorie löschen; Einträge und Spalten werden auf null gesetzt.
     */
    public function deleteCategory(PaedDiaryCategory $category)
    {
        $user = Auth::user();

        if (! $category->isOwnedBy($user->id)) {
            return response()->json(['message' => 'Keine Berechtigung'], 403);
        }

        PaedDiaryEntry::where('category_id', $category->id)->update(['category_id' => null]);
        PaedDiaryColumn::where('category', $category->name)->update(['category' => null]);

        $category->delete();

        return response()->json(['success' => true]);
    }

    // ── Toggle Sichtbarkeit ───────────────────────────────────────────────────

    /**
     * POST /paed-diary/categories/{category}/toggle-hidden
     * Schaltet die Sichtbarkeit einer Kategorie für den aktuellen User um.
     */
    public function toggleHidden(PaedDiaryCategory $category)
    {
        $user = Auth::user();

        if (! $category->isGlobal() && ! $category->isOwnedBy($user->id)) {
            return response()->json(['message' => 'Keine Berechtigung'], 403);
        }

        $isHidden = $user->hiddenPaedDiaryCategories()
            ->where('paed_diary_category_id', $category->id)
            ->exists();

        if ($isHidden) {
            $user->hiddenPaedDiaryCategories()->detach($category->id);
        } else {
            $user->hiddenPaedDiaryCategories()->attach($category->id);
        }

        return response()->json(['success' => true, 'hidden' => ! $isHidden]);
    }

    /**
     * GET /paed-diary/categories/hidden
     * JSON: IDs der vom aktuellen User ausgeblendeten Kategorien.
     */
    public function getHiddenCategories()
    {
        $ids = Auth::user()->hiddenPaedDiaryCategories()
            ->pluck('paed_diary_categories.id');

        return response()->json(['hidden_category_ids' => $ids]);
    }

    // ── Globale Kategorien (Permission: manage global paed diary categories) ──

    /**
     * POST /paed-diary/categories/global
     * Globale Kategorie erstellen.
     */
    public function storeGlobalCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $name = trim($data['name']);

        if (PaedDiaryCategory::whereNull('user_id')->where('name', $name)->exists()) {
            return response()->json(['message' => 'Globale Kategorie mit diesem Namen existiert bereits'], 422);
        }

        $category = PaedDiaryCategory::create(['name' => $name, 'user_id' => null]);

        return response()->json([
            'success'  => true,
            'category' => [
                'id'         => $category->id,
                'name'       => $category->name,
                'is_global'  => true,
                'can_edit'   => true,
                'can_delete' => true,
            ],
        ]);
    }

    /**
     * PUT /paed-diary/categories/global/{category}
     * Globale Kategorie bearbeiten.
     */
    public function updateGlobalCategory(PaedDiaryCategory $category, Request $request)
    {
        if (! $category->isGlobal()) {
            return response()->json(['message' => 'Keine globale Kategorie'], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $newName = trim($data['name']);

        $exists = PaedDiaryCategory::whereNull('user_id')
            ->where('name', $newName)
            ->where('id', '!=', $category->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Kategorie mit diesem Namen existiert bereits'], 422);
        }

        $category->name = $newName;
        $category->save();

        return response()->json(['success' => true, 'name' => $category->name]);
    }

    /**
     * DELETE /paed-diary/categories/global/{category}
     * Globale Kategorie löschen.
     */
    public function deleteGlobalCategory(PaedDiaryCategory $category)
    {
        if (! $category->isGlobal()) {
            return response()->json(['message' => 'Keine globale Kategorie'], 422);
        }

        PaedDiaryEntry::where('category_id', $category->id)->update(['category_id' => null]);
        PaedDiaryColumn::where('category', $category->name)->update(['category' => null]);
        $category->delete();

        return response()->json(['success' => true]);
    }

    // ── Spaltengruppen ────────────────────────────────────────────────────────

    /**
     * GET /paed-diary/column-groups
     * JSON: Alle verwendeten Spaltengruppen-Namen mit Anzahl Spalten.
     */
    public function getColumnGroups()
    {
        $groups = PaedDiaryColumn::whereNotNull('category')
            ->where('category', '!=', '')
            ->groupBy('category')
            ->selectRaw('category as name, COUNT(*) as count')
            ->orderBy('category')
            ->get();

        return response()->json(['groups' => $groups]);
    }

    /**
     * POST /paed-diary/column-groups/rename
     * Bulk-Rename einer Spaltengruppe (alle Spalten mit altem Namen).
     */
    public function renameColumnGroup(Request $request)
    {
        $data = $request->validate([
            'old_name' => ['required', 'string', 'max:100'],
            'new_name' => ['required', 'string', 'max:100'],
        ]);

        $count = PaedDiaryColumn::where('category', trim($data['old_name']))
            ->update(['category' => trim($data['new_name'])]);

        return response()->json(['success' => true, 'updated' => $count]);
    }
}

