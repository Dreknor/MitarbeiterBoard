<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wochenplan\WpAufgabeRequest;
use App\Models\PaedDiaryTask;
use App\Models\Wochenplan\WpAufgabe;
use App\Models\Wochenplan\WpPlanFach;
use Illuminate\Http\Request;

class WpAufgabeController extends Controller
{
    public function store(WpAufgabeRequest $request, WpPlanFach $wpPlanFach)
    {
        $maxOrder = $wpPlanFach->aufgaben()->max('sort_order') ?? -1;

        $wpPlanFach->aufgaben()->create([
            'aufgabe'    => $request->validated('aufgabe'),
            'dauer'      => $request->validated('dauer'),
            'sort_order' => $maxOrder + 1,
        ]);

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Aufgabe hinzugefügt.']);
    }

    public function update(WpAufgabeRequest $request, WpAufgabe $wpAufgabe)
    {
        $data = $request->validated();
        $data['synced_from_id'] = null; // Sync-Status aufheben bei Bearbeitung

        $wpAufgabe->update($data);

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Aufgabe aktualisiert.']);
    }

    public function destroy(WpAufgabe $wpAufgabe)
    {
        $wpAufgabe->delete();

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Aufgabe gelöscht.']);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order'              => 'required|array',
            'order.*.id'         => 'required|exists:wp_aufgaben,id',
            'order.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->input('order') as $item) {
            WpAufgabe::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Übernimmt eine offene Tagebuch-Aufgabe als WP-Aufgabe in ein bestimmtes Fach.
     *
     * POST /wp/aufgabe/aus-tagebuch/{wpPlanFach}
     */
    public function storeFromDiaryTask(Request $request, WpPlanFach $wpPlanFach)
    {
        $data = $request->validate([
            'diary_task_id' => ['required', 'integer', 'exists:paed_diary_tasks,id'],
            'aufgabe'       => ['required', 'string', 'max:1000'],
            'dauer'         => ['nullable', 'string', 'max:50'],
        ]);

        // Sicherheitsprüfung: Tagebuch-Aufgabe gehört zum gleichen Schüler
        $diaryTask = PaedDiaryTask::findOrFail($data['diary_task_id']);
        $plan = $wpPlanFach->plan;

        if (!$plan->isSchuelerplan() || $plan->schueler_id !== $diaryTask->schueler_id) {
            abort(403, 'Die Tagebuch-Aufgabe gehört nicht zum Schüler dieses Plans.');
        }

        $maxOrder = $wpPlanFach->aufgaben()->max('sort_order') ?? 0;

        WpAufgabe::create([
            'wp_plan_fach_id' => $wpPlanFach->id,
            'aufgabe'         => $data['aufgabe'],
            'dauer'           => $data['dauer'] ?? null,
            'sort_order'      => $maxOrder + 1,
            'synced_from_id'  => null,
        ]);

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Aufgabe aus dem Tagebuch wurde übernommen.',
        ]);
    }
}
