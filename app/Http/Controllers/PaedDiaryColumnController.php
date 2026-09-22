<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\PaedDiaryHelperTrait;
use App\Models\PaedDiaryClassGroup;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryColumnValue;
use App\Models\Schueler;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class PaedDiaryColumnController extends Controller
{
    use PaedDiaryHelperTrait;

    public function storeColumn(Request $request)
    {
        $data = $request->validate([
            'klasse_id'   => ['nullable', 'integer', 'exists:klassen,id'],
            'klasse_ids'  => ['nullable', 'array'],
            'klasse_ids.*' => ['integer', 'exists:klassen,id'],
            'group_id'  => ['nullable', 'integer', 'exists:paed_diary_class_groups,id'],
            'name'      => ['required', 'string', 'max:50'],
            'type'      => ['nullable', 'in:text,boolean,number,ampel'],
            'category'  => ['nullable', 'string', 'max:50'],
        ]);
        if (!$request->filled('klasse_id') && !$request->filled('klasse_ids') && !$request->filled('group_id')) {
            return response()->json(['message' => 'klasse_id, klasse_ids oder group_id erforderlich'], 422);
        }
        $user     = Auth::user();
        $baseSlug = \Illuminate\Support\Str::slug($data['name']) ?: $data['name'];
        $type     = $data['type'] ?? 'text';
        $category = $data['category'] ?? null;

        if ($request->filled('group_id')) {
            $group          = PaedDiaryClassGroup::with('klassen:id')->where('id', $request->group_id)->where('user_id', $user->id)->firstOrFail();
            $userKlassenIds = $user->paed_klassen()->pluck('klassen.id');
            $created        = [];
            foreach ($group->klassen->whereIn('id', $userKlassenIds) as $klasse) {
                $slug    = $this->generateUniqueSlug($baseSlug, $klasse->id);
                $sort    = (int) PaedDiaryColumn::where('klasse_id', $klasse->id)->max('sort_order') + 1;
                $colData = ['klasse_id' => $klasse->id, 'name' => $data['name'], 'slug' => $slug, 'type' => $type, 'sort_order' => $sort];
                if ($category) $colData['category'] = $category;
                $col     = PaedDiaryColumn::create($colData);
                $this->forgetWeekCache($klasse->id, Carbon::now());
                $created[] = ['id' => $col->id, 'klasse_id' => $klasse->id, 'name' => $col->name, 'category' => $col->category ?? null];
            }
            return response()->json(['success' => true, 'columns' => $created]);
        }

        // Eine oder mehrere Klassen (Mehrfachauswahl) direkt angeben.
        $targetIds = $request->filled('klasse_ids') ? $data['klasse_ids'] : [$data['klasse_id']];
        $klassen   = $user->paed_klassen()->whereIn('klassen.id', $targetIds)->get();
        if ($klassen->isEmpty()) abort(404);

        $created = [];
        foreach ($klassen as $klasse) {
            $slug    = $this->generateUniqueSlug($baseSlug, $klasse->id);
            $sort    = (int) PaedDiaryColumn::where('klasse_id', $klasse->id)->max('sort_order') + 1;
            $colData = ['klasse_id' => $klasse->id, 'name' => $data['name'], 'slug' => $slug, 'type' => $type, 'sort_order' => $sort];
            if ($category) $colData['category'] = $category;
            $col = PaedDiaryColumn::create($colData);
            $this->forgetWeekCache($klasse->id, Carbon::now());
            $created[] = ['id' => $col->id, 'klasse_id' => $klasse->id, 'name' => $col->name, 'category' => $col->category ?? null];
        }

        if (count($created) === 1) {
            return response()->json(['success' => true, 'column' => $created[0]]);
        }
        return response()->json(['success' => true, 'columns' => $created]);
    }

    /**
     * Kopiert eine bestehende Spalte in eine oder mehrere weitere Klassen.
     * Existiert in einer Zielklasse bereits eine aktive Spalte mit gleichem Namen,
     * wird diese Zielklasse übersprungen (kein Duplikat, keine Fehlermeldung).
     */
    public function copyColumn(PaedDiaryColumn $column, Request $request)
    {
        $data = $request->validate([
            'klasse_ids'   => ['required', 'array', 'min:1'],
            'klasse_ids.*' => ['integer', 'exists:klassen,id'],
        ]);
        $user = Auth::user();
        // Sicherstellen, dass der Nutzer Zugriff auf die Quellklasse der Spalte hat.
        $user->paed_klassen()->where('klassen.id', $column->klasse_id)->firstOrFail();

        $targetKlassen = $user->paed_klassen()->whereIn('klassen.id', $data['klasse_ids'])->get();
        $baseSlug      = \Illuminate\Support\Str::slug($column->name) ?: $column->name;

        $created = [];
        $skipped = [];
        foreach ($targetKlassen as $klasse) {
            if ((int) $klasse->id === (int) $column->klasse_id) continue;

            $exists = PaedDiaryColumn::where('klasse_id', $klasse->id)
                ->where('name', $column->name)
                ->whereNull('deactivated_from')
                ->exists();
            if ($exists) {
                $skipped[] = ['klasse_id' => $klasse->id, 'name' => $klasse->name];
                continue;
            }

            $slug    = $this->generateUniqueSlug($baseSlug, $klasse->id);
            $sort    = (int) PaedDiaryColumn::where('klasse_id', $klasse->id)->max('sort_order') + 1;
            $colData = ['klasse_id' => $klasse->id, 'name' => $column->name, 'slug' => $slug, 'type' => $column->type, 'sort_order' => $sort];
            if ($column->category) $colData['category'] = $column->category;
            $col = PaedDiaryColumn::create($colData);
            $this->forgetWeekCache($klasse->id, Carbon::now());
            $created[] = ['id' => $col->id, 'klasse_id' => $klasse->id, 'name' => $klasse->name];
        }

        return response()->json(['success' => true, 'created' => $created, 'skipped' => $skipped]);
    }

    public function destroyColumn(PaedDiaryColumn $column, Request $request)
    {
        $data = $request->validate([
            'klasse_id' => ['required', 'integer', 'exists:klassen,id'],
        ]);
        $user = Auth::user();
        if ($column->klasse_id != $data['klasse_id']) abort(403);
        $klasse = $user->paed_klassen()->where('klassen.id', $column->klasse_id)->firstOrFail();
        try {
            $weekStart = $request->filled('week_start')
                ? Carbon::parse($request->week_start)->startOfWeek()
                : Carbon::now()->startOfWeek();
            if (is_null($column->deactivated_from) || $column->deactivated_from->gt($weekStart)) {
                $column->deactivated_from = $weekStart->toDateString();
                $column->save();
            }
            $this->forgetWeekCache($klasse->id, $weekStart);
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            Log::error('destroyColumn error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Fehler beim Deaktivieren der Spalte.'], 500);
        }
    }

    public function storeColumnValue(Request $request)
    {
        $data = $request->validate([
            'column_id'   => ['required', 'integer', 'exists:paed_diary_columns,id'],
            'schueler_id' => ['required', 'integer', 'exists:schueler,id'],
            'date'        => ['required', 'date'],
            'value'       => ['nullable', 'string', 'max:255'],
        ]);
        $column  = PaedDiaryColumn::findOrFail($data['column_id']);
        $user    = Auth::user();
        $klasse  = $user->paed_klassen()->where('klassen.id', $column->klasse_id)->firstOrFail();
        $schueler = Schueler::where('id', $data['schueler_id'])->where('klasse_id', $klasse->id)->firstOrFail();
        $dateObj = Carbon::parse($data['date']);
        PaedDiaryColumnValue::updateOrCreate([
            'paed_diary_column_id' => $column->id,
            'schueler_id'          => $schueler->id,
            'datum'                => $dateObj->toDateString(),
        ], ['value' => $data['value']]);
        $this->forgetWeekCache($klasse->id, $dateObj);
        return response()->json(['success' => true]);
    }

    public function updateColumnCategory(PaedDiaryColumn $column, Request $request)
    {
        $data   = $request->validate(['category' => ['nullable', 'string', 'max:50']]);
        $user   = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id', $column->klasse_id)->firstOrFail();
        $column->category = $data['category'] ?? null;
        $column->save();
        $this->forgetWeekCache($klasse->id, Carbon::now());
        return response()->json(['success' => true, 'category' => $column->category]);
    }

    public function columnsAll(Request $request)
    {
        $data   = $request->validate(['klasse_id' => ['required', 'integer', 'exists:klassen,id']]);
        $user   = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id', $data['klasse_id'])->firstOrFail();
        $cols   = PaedDiaryColumn::where('klasse_id', $klasse->id)->orderBy('sort_order')->get()->map(fn ($c) => [
            'id'               => $c->id,
            'name'             => $c->name,
            'type'             => $c->type,
            'sort_order'       => $c->sort_order,
            'deactivated_from' => $c->deactivated_from?->toDateString(),
            'category'         => $c->category ?? null,
        ]);
        return response()->json(['columns' => $cols]);
    }

    public function restoreColumn(PaedDiaryColumn $column, Request $request)
    {
        $user   = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id', $column->klasse_id)->firstOrFail();
        if ($column->deactivated_from) {
            $column->deactivated_from = null;
            $column->save();
            $this->forgetWeekCache($klasse->id, Carbon::now());
        }
        return response()->json(['success' => true]);
    }
}

