<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Models\Wochenplan\WpPlan;
use Illuminate\Http\Request;

class WpVorlageController extends Controller
{
    public function index()
    {
        $vorlagen = WpPlan::vorlagen()
            ->with(['planFaecher.fach', 'planFaecher.aufgaben', 'creator'])
            ->orderBy('name')
            ->get();
        return view('wochenplan.new.vorlagen.index', compact('vorlagen'));
    }

    public function alsVorlageSpeichern(Request $request, WpPlan $wpPlan)
    {
        $request->validate(['vorlage_name' => 'required|string|max:255']);

        $wpPlan->duplizieren([
            'is_vorlage'  => true,
            'vorlage_name'=> $request->vorlage_name,
            'name'        => $request->vorlage_name,
            'klasse_id'   => null,
            'schueler_id' => null,
        ]);

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Als Vorlage gespeichert.']);
    }

    public function vonVorlageErstellen(Request $request, WpPlan $wpPlan)
    {
        $request->validate([
            'name'                => 'required|string|max:255',
            'klasse_id'           => 'nullable|exists:klassen,id',
            'gueltig_von'         => 'required|date',
            'gueltig_bis'         => 'required|date|after_or_equal:gueltig_von',
            'selbsteinschaetzung' => 'nullable|integer|in:0,1,2',
            'formatvorlage_id'    => 'nullable|exists:wp_formatvorlagen,id',
        ]);

        $neuerPlan = $wpPlan->duplizieren([
            'name'                => $request->input('name'),
            'klasse_id'           => $request->input('klasse_id'),
            'gueltig_von'         => $request->input('gueltig_von'),
            'gueltig_bis'         => $request->input('gueltig_bis'),
            'selbsteinschaetzung' => $request->input('selbsteinschaetzung', 0),
            'formatvorlage_id'    => $request->input('formatvorlage_id'),
            'vorlage_id'          => $wpPlan->id,
            'is_vorlage'          => false,
            'vorlage_name'        => null,
            'schueler_id'         => null,
            'created_by'          => auth()->id(),
        ]);

        return redirect()->route('wp.edit', $neuerPlan)
            ->with(['type' => 'success', 'Meldung' => 'Plan aus Vorlage erstellt.']);
    }

    public function destroy(WpPlan $wpPlan)
    {
        $wpPlan->delete();

        return redirect()->route('wp.vorlagen.index')
            ->with(['type' => 'success', 'Meldung' => 'Vorlage gelöscht.']);
    }
}
