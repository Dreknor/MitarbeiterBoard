<?php

namespace App\Http\Controllers;

use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaedDiaryCategoryController extends Controller
{
    /** Erlaubte Farbangabe: Hex-Farbe wie #1d4ed8 */
    private const FARBE = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

    // ── Verwaltungs-View ─────────────────────────────────────────────────────

    /**
     * GET /paed-diary/categories/manage
     * Verwaltung der Notizkategorien (Name, Farbe, Sichtbarkeit) und der Spaltengruppen.
     */
    public function manageView()
    {
        $canManageGlobal = Auth::user()->can('manage global paed diary categories');

        return view('paedDiary.categories.index', compact('canManageGlobal'));
    }

    // ── Eigene Kategorien ─────────────────────────────────────────────────────

    /**
     * GET /paed-diary/categories
     * JSON: Alle Kategorien (global + eigene des Users) inkl. Farbe, Sichtbarkeit und Anzahl Einträge.
     */
    public function getCategories()
    {
        $user = Auth::user();

        $categories = PaedDiaryCategory::forUser($user->id)
            ->withCount('entries')
            ->orderByRaw('user_id IS NULL DESC')   // global zuerst
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'user_id']);

        $ausgeblendet = $user->hiddenPaedDiaryCategories()->pluck('paed_diary_categories.id')->map(fn ($id) => (int) $id)->all();

        return response()->json([
            'categories' => $categories->map(fn ($c) => $this->alsDaten($c, $user, $ausgeblendet)),
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
            'color' => self::FARBE,
        ]);

        $name = trim($data['name']);

        // Duplikat prüfen (global oder eigene)
        if (PaedDiaryCategory::forUser($user->id)->where('name', $name)->exists()) {
            return response()->json(['message' => 'Kategorie mit diesem Namen existiert bereits'], 422);
        }

        $category = PaedDiaryCategory::create([
            'name'    => $name,
            'color'   => $this->farbe($data),
            'user_id' => $user->id,
        ]);

        return response()->json(['success' => true, 'category' => $this->alsDaten($category, $user)]);
    }

    /**
     * PUT /paed-diary/categories/{category}/rename
     * Eigene Kategorie umbenennen und/oder Farbe ändern.
     */
    public function renameCategory(PaedDiaryCategory $category, Request $request)
    {
        $user = Auth::user();

        if (! $category->isOwnedBy($user->id)) {
            return response()->json(['message' => 'Keine Berechtigung'], 403);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'color' => self::FARBE,
        ]);

        if (array_key_exists('name', $data)) {
            $newName = trim($data['name']);
            $exists = PaedDiaryCategory::where('name', $newName)
                ->where('user_id', $user->id)
                ->where('id', '!=', $category->id)
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'Kategorie mit diesem Namen existiert bereits'], 422);
            }
            $category->name = $newName;
        }
        if (array_key_exists('color', $data)) {
            $category->color = $this->farbe($data);
        }
        $category->save();

        return response()->json(['success' => true, 'name' => $category->name, 'color' => $category->color]);
    }

    /**
     * DELETE /paed-diary/categories/{category}
     * Eigene Kategorie löschen; Einträge werden auf „ohne Kategorie“ gesetzt. Spaltengruppen sind
     * davon unabhängig (gleichnamige Gruppen anderer Klassen bleiben erhalten).
     */
    public function deleteCategory(PaedDiaryCategory $category)
    {
        $user = Auth::user();

        if (! $category->isOwnedBy($user->id)) {
            return response()->json(['message' => 'Keine Berechtigung'], 403);
        }

        PaedDiaryEntry::where('category_id', $category->id)->update(['category_id' => null]);

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
            'color' => self::FARBE,
        ]);

        $name = trim($data['name']);

        if (PaedDiaryCategory::whereNull('user_id')->where('name', $name)->exists()) {
            return response()->json(['message' => 'Globale Kategorie mit diesem Namen existiert bereits'], 422);
        }

        $category = PaedDiaryCategory::create(['name' => $name, 'color' => $this->farbe($data), 'user_id' => null]);

        return response()->json(['success' => true, 'category' => $this->alsDaten($category, Auth::user())]);
    }

    /**
     * PUT /paed-diary/categories/global/{category}
     * Globale Kategorie umbenennen und/oder Farbe ändern.
     */
    public function updateGlobalCategory(PaedDiaryCategory $category, Request $request)
    {
        if (! $category->isGlobal()) {
            return response()->json(['message' => 'Keine globale Kategorie'], 422);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'color' => self::FARBE,
        ]);

        if (array_key_exists('name', $data)) {
            $newName = trim($data['name']);
            $exists = PaedDiaryCategory::whereNull('user_id')
                ->where('name', $newName)
                ->where('id', '!=', $category->id)
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'Kategorie mit diesem Namen existiert bereits'], 422);
            }
            $category->name = $newName;
        }
        if (array_key_exists('color', $data)) {
            $category->color = $this->farbe($data);
        }
        $category->save();

        return response()->json(['success' => true, 'name' => $category->name, 'color' => $category->color]);
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

    // ── Hilfen ────────────────────────────────────────────────────────────────

    /** Farbe aus validierten Daten (leer = keine Farbe), einheitlich klein geschrieben */
    private function farbe(array $data): ?string
    {
        $farbe = $data['color'] ?? null;

        return $farbe ? strtolower($farbe) : null;
    }

    /**
     * Antwortformat einer Kategorie für die Verwaltung.
     *
     * @param int[]|null $ausgeblendet IDs der vom Benutzer ausgeblendeten Kategorien
     */
    private function alsDaten(PaedDiaryCategory $c, User $user, ?array $ausgeblendet = null): array
    {
        $canManageGlobal = $user->can('manage global paed diary categories');
        $darf = $c->isGlobal() ? $canManageGlobal : $c->isOwnedBy($user->id);

        return [
            'id'            => $c->id,
            'name'          => $c->name,
            'color'         => $c->color,
            'is_global'     => $c->isGlobal(),
            'can_edit'      => $darf,
            'can_delete'    => $darf,
            'hidden'        => $ausgeblendet !== null && in_array((int) $c->id, $ausgeblendet, true),
            'entries_count' => (int) ($c->entries_count ?? 0),
        ];
    }
}
