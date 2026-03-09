<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wochenplan\WpFachRequest;
use App\Models\Wochenplan\WpFach;

class WpFachController extends Controller
{
    public function index()
    {
        $faecher = WpFach::ordered()->withCount('planFaecher')->get();
        return view('wochenplan.new.faecher.index', compact('faecher'));
    }

    public function store(WpFachRequest $request)
    {
        $maxOrder = WpFach::max('sort_order') ?? -1;

        WpFach::create([
            'name'         => $request->validated('name'),
            'sort_order'   => $request->input('sort_order', $maxOrder + 1),
            'is_default'   => $request->boolean('is_default', false),
            'symbol_typ'   => $request->input('symbol_typ', 'keine'),
            'symbol_wert'  => $request->input('symbol_wert'),
            'symbol_farbe' => $request->input('symbol_farbe'),
        ]);

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Fach wurde hinzugefügt.']);
    }

    public function update(WpFachRequest $request, WpFach $wpFach)
    {
        $wpFach->update(array_merge($request->validated(), [
            'symbol_typ'   => $request->input('symbol_typ', 'keine'),
            'symbol_wert'  => $request->input('symbol_wert'),
            'symbol_farbe' => $request->input('symbol_farbe'),
        ]));

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Fach wurde aktualisiert.']);
    }

    public function destroy(WpFach $wpFach)
    {
        if ($wpFach->planFaecher()->exists()) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Das Fach "' . $wpFach->name . '" wird in Plänen verwendet und kann nicht gelöscht werden.',
            ]);
        }

        $wpFach->delete();

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Fach wurde gelöscht.']);
    }
}

