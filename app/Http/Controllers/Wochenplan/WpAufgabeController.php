<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wochenplan\WpAufgabeRequest;
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
}
