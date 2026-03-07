<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wochenplan\WpFachRequest;
use App\Models\Wochenplan\WpFach;

class WpFachController extends Controller
{
    public function index()
    {
        $faecher = WpFach::ordered()->get();
        return view('wochenplan.new.faecher.index', compact('faecher'));
    }

    public function store(WpFachRequest $request)
    {
        $maxOrder = WpFach::max('sort_order') ?? -1;
        WpFach::create(array_merge($request->validated(), [
            'sort_order' => $request->input('sort_order', $maxOrder + 1),
        ]));

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Fach wurde hinzugefügt.']);
    }

    public function update(WpFachRequest $request, WpFach $wpFach)
    {
        $wpFach->update($request->validated());

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Fach wurde aktualisiert.']);
    }

    public function destroy(WpFach $wpFach)
    {
        $wpFach->delete();

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Fach wurde gelöscht.']);
    }
}
