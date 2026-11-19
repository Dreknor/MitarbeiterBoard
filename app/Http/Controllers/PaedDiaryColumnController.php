<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\PaedDiaryHelperTrait;
use App\Models\PaedDiaryClassGroup;
use App\Models\PaedDiaryColumn;
use App\Models\Schueler;
use App\Services\PaedDiarySpaltenService;
use App\Services\PaedDiarySpaltenwertService;
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
            $created[] = ['id' => $col->id, 'klasse_id' => $klasse->id, 'name' => $col->name, 'category' => $col->category ?? null];
        }

        if (count($created) === 1) {
            return response()->json(['success' => true, 'column' => $created[0]]);
        }
        return response()->json(['success' => true, 'columns' => $created]);
    }

    /**
     * Kopiert eine bestehende Spalte in eine oder mehrere weitere Klassen. Die Kopien bleiben mit
     * der Quelle verknüpft (gleiche spalten_uid). Hat die Zielklasse eine deaktivierte Kopie,
     * wird diese reaktiviert. Gibt es dort bereits eine aktive Spalte mit gleichem Namen in
     * derselben Gruppe, wird die Zielklasse übersprungen (kein Duplikat, keine Fehlermeldung).
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

        $spalten       = app(PaedDiarySpaltenService::class);
        $kopien        = $spalten->kopien($column);
        $targetKlassen = $user->paed_klassen()->whereIn('klassen.id', $data['klasse_ids'])->get();

        $created = [];
        $skipped = [];
        foreach ($targetKlassen as $klasse) {
            if ((int) $klasse->id === (int) $column->klasse_id) continue;

            $vorhandeneKopie = $spalten->kopieInKlasse($kopien, (int) $klasse->id);
            if ($vorhandeneKopie && $vorhandeneKopie->deactivated_from) {
                $vorhandeneKopie->deactivated_from = null;
                $vorhandeneKopie->save();
                $created[] = ['id' => $vorhandeneKopie->id, 'klasse_id' => $klasse->id, 'name' => $klasse->name];
                continue;
            }

            $exists = $vorhandeneKopie || PaedDiaryColumn::where('klasse_id', $klasse->id)
                ->gleichBenannt($column->name, $column->category)
                ->whereNull('deactivated_from')
                ->exists();
            if ($exists) {
                $skipped[] = ['klasse_id' => $klasse->id, 'name' => $klasse->name];
                continue;
            }

            $colData = [
                'klasse_id'   => $klasse->id,
                'spalten_uid' => $column->spalten_uid,
                'name'        => $column->name,
                'slug'        => $spalten->eindeutigerSlug($column->name, (int) $klasse->id),
                'type'        => $column->type,
                'sort_order'  => (int) PaedDiaryColumn::where('klasse_id', $klasse->id)->max('sort_order') + 1,
            ];
            if ($column->category) $colData['category'] = $column->category;
            $col = PaedDiaryColumn::create($colData);
            $created[] = ['id' => $col->id, 'klasse_id' => $klasse->id, 'name' => $klasse->name];
        }

        return response()->json(['success' => true, 'created' => $created, 'skipped' => $skipped]);
    }

    /**
     * Daten für den Bearbeiten-Dialog einer Spalte (Name, Gruppe, Art, Klassen).
     */
    public function editColumn(PaedDiaryColumn $column, PaedDiarySpaltenService $spalten)
    {
        $user = Auth::user();
        abort_unless($spalten->darfBearbeiten($user, $column), 404);

        return response()->json([
            'spalte'     => $spalten->uebersicht($column, $user),
            'kategorien' => PaedDiaryColumn::whereNotNull('category')->where('category', '!=', '')
                ->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    /**
     * Spalte bearbeiten: Name, Gruppe und Art gelten für alle Kopien, die Klassenauswahl
     * legt Kopien an, reaktiviert oder deaktiviert sie (PaedDiarySpaltenService).
     */
    public function updateColumn(PaedDiaryColumn $column, Request $request, PaedDiarySpaltenService $spalten)
    {
        $user = Auth::user();
        abort_unless($spalten->darfBearbeiten($user, $column), 404);

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:50'],
            'category'     => ['nullable', 'string', 'max:50'],
            'type'         => ['required', 'in:' . implode(',', PaedDiarySpaltenService::TYPEN)],
            'klasse_ids'   => ['present', 'array'],
            'klasse_ids.*' => ['integer', 'exists:klassen,id'],
        ]);
        if (trim($data['name']) === '') {
            return response()->json(['message' => 'Bitte einen Namen eingeben.', 'errors' => ['name' => ['Bitte einen Namen eingeben.']]], 422);
        }

        $ergebnis = $spalten->aktualisieren($column, $user, $data);

        return response()->json([
            'success'  => true,
            'ergebnis' => $ergebnis,
            'spalte'   => $spalten->uebersicht($column->fresh(), $user),
        ]);
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

        // Wert passend zum Spaltentyp: Ja/Nein ('' | '1'), Ampel ('' | '1' Ja | '2' In Bearbeitung | '3' Nein)
        $erlaubt = match ($column->type) {
            'boolean' => ['', '1'],
            'ampel' => ['', '1', '2', '3'],
            default => null,
        };
        if ($erlaubt !== null && !in_array((string) ($data['value'] ?? ''), $erlaubt, true)) {
            return response()->json(['message' => 'Ungültiger Wert für diese Spalte.', 'errors' => ['value' => ['Ungültiger Wert für diese Spalte.']]], 422);
        }
        $spaltenwerte = app(PaedDiarySpaltenwertService::class);
        $spaltenwerte->speichern($column, $schueler->id, $dateObj, $data['value'] ?? null);

        // Gültige Werte der ganzen Woche zurückgeben – bei „bis Wochenende“ ändern sich auch Folgetage.
        $woche = $spaltenwerte->woche($column, $schueler->id, $dateObj);
        return response()->json([
            'success' => true,
            'werte'   => collect($woche)->mapWithKeys(fn ($t) => [$t['date'] => $t['value']]),
            'quellen' => collect($woche)->filter(fn ($t) => $t['carried_from'])->mapWithKeys(fn ($t) => [$t['date'] => $t['carried_from']]),
        ]);
    }

    /**
     * Nur die Spaltengruppe ändern – gilt wie im Bearbeiten-Dialog für alle Kopien der Spalte.
     */
    public function updateColumnCategory(PaedDiaryColumn $column, Request $request, PaedDiarySpaltenService $spalten)
    {
        $data   = $request->validate(['category' => ['nullable', 'string', 'max:50']]);
        $user   = Auth::user();
        $user->paed_klassen()->where('klassen.id', $column->klasse_id)->firstOrFail();
        // Die Kopien übernehmen dabei die Einstellung „bis Wochenende“ der neuen Gruppe (PaedDiaryColumn::booted)
        $spalten->aktualisieren($column, $user, [
            'name'       => $column->name,
            'category'   => $data['category'] ?? null,
            'type'       => $column->type,
            'klasse_ids' => null,
        ]);
        $column->refresh();
        return response()->json(['success' => true, 'category' => $column->category, 'bis_wochenende' => (bool) $column->bis_wochenende]);
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
            'bis_wochenende'   => (bool) $c->bis_wochenende,
        ]);
        return response()->json([
            'columns' => $cols,
            // „bis Wochenende“ umschalten betrifft alle Klassen – gleiches Recht wie Spaltengruppen umbenennen
            'darf_bis_wochenende' => $user->can('manage global paed diary categories'),
        ]);
    }

    public function restoreColumn(PaedDiaryColumn $column, Request $request)
    {
        $user   = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id', $column->klasse_id)->firstOrFail();
        if ($column->deactivated_from) {
            $column->deactivated_from = null;
            $column->save();
        }
        return response()->json(['success' => true]);
    }
}

