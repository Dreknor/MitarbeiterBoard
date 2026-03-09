<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Models\Wochenplan\WpPlan;
use App\Models\Wochenplan\WpTaeglicheUebung;
use Illuminate\Http\Request;

class WpTaeglicheUebungController extends Controller
{
    /**
     * Tägliche Übungen aktivieren/deaktivieren.
     */
    public function toggle(WpPlan $wpPlan)
    {
        $wpPlan->update([
            'taegliche_uebungen_aktiv' => !$wpPlan->taegliche_uebungen_aktiv,
        ]);

        $status = $wpPlan->taegliche_uebungen_aktiv ? 'aktiviert' : 'deaktiviert';

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => "Tägliche Übungen {$status}."]);
    }

    /**
     * Neue tägliche Übung hinzufügen.
     */
    public function store(Request $request, WpPlan $wpPlan)
    {
        $request->validate([
            'aufgabe' => 'required|string|max:1000',
        ]);

        $maxOrder = $wpPlan->taeglicheUebungen()->max('sort_order') ?? -1;

        $wpPlan->taeglicheUebungen()->create([
            'aufgabe'    => $request->input('aufgabe'),
            'sort_order' => $maxOrder + 1,
        ]);

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Übung hinzugefügt.']);
    }

    /**
     * Tägliche Übung bearbeiten.
     */
    public function update(Request $request, WpTaeglicheUebung $wpTaeglicheUebung)
    {
        $request->validate([
            'aufgabe' => 'required|string|max:1000',
        ]);

        $wpTaeglicheUebung->update([
            'aufgabe' => $request->input('aufgabe'),
        ]);

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Übung aktualisiert.']);
    }

    /**
     * Tägliche Übung löschen.
     */
    public function destroy(WpTaeglicheUebung $wpTaeglicheUebung)
    {
        $wpTaeglicheUebung->delete();

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Übung gelöscht.']);
    }
}

