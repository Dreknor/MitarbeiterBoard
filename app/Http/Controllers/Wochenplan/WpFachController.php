<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wochenplan\WpFachRequest;
use App\Models\Wochenplan\WpFach;
use Illuminate\Support\Facades\Storage;

class WpFachController extends Controller
{
    public function index()
    {
        $faecher = WpFach::ordered()->withCount('planFaecher')->get();
        return view('wochenplan.new.faecher.index', compact('faecher'));
    }

    public function store(WpFachRequest $request)
    {
        $maxOrder   = WpFach::max('sort_order') ?? -1;
        $symbolTyp  = $request->input('symbol_typ', 'keine');
        $symbolWert = null;

        if ($symbolTyp === 'bild') {
            if ($request->hasFile('symbol_bild') && $request->file('symbol_bild')->isValid()) {
                $symbolWert = $request->file('symbol_bild')
                    ->store('wp-fach-symbole', 'public');
            }
            // Kein Bild hochgeladen → Typ auf 'keine' zurücksetzen
            if (!$symbolWert) {
                $symbolTyp = 'keine';
            }
        } else {
            $symbolWert = $request->input('symbol_wert');
        }

        WpFach::create([
            'name'         => $request->validated('name'),
            'sort_order'   => $request->input('sort_order', $maxOrder + 1),
            'is_default'   => $request->boolean('is_default', false),
            'symbol_typ'   => $symbolTyp,
            'symbol_wert'  => $symbolWert,
            'symbol_farbe' => $request->input('symbol_farbe'),
        ]);

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Fach wurde hinzugefügt.']);
    }

    public function update(WpFachRequest $request, WpFach $wpFach)
    {
        $symbolTyp  = $request->input('symbol_typ', 'keine');
        $symbolWert = $wpFach->symbol_wert; // Standardmäßig alten Wert behalten

        if ($symbolTyp === 'bild') {
            if ($request->hasFile('symbol_bild') && $request->file('symbol_bild')->isValid()) {
                // Altes Bild löschen
                if ($wpFach->symbol_typ === 'bild' && $wpFach->symbol_wert) {
                    Storage::disk('public')->delete($wpFach->symbol_wert);
                }
                $symbolWert = $request->file('symbol_bild')
                    ->store('wp-fach-symbole', 'public');
            } elseif ($wpFach->symbol_typ !== 'bild') {
                // Typ wurde von etwas anderem auf 'bild' geändert, aber kein Bild hochgeladen
                $symbolTyp  = 'keine';
                $symbolWert = null;
            }
            // Sonst: symbol_typ bleibt 'bild', symbol_wert bleibt der alte Bildpfad
        } else {
            // Typ auf Emoji/SVG/keine geändert → altes Bild löschen
            if ($wpFach->symbol_typ === 'bild' && $wpFach->symbol_wert) {
                Storage::disk('public')->delete($wpFach->symbol_wert);
            }
            $symbolWert = $request->input('symbol_wert');
        }

        $wpFach->update([
            'name'         => $request->validated('name'),
            'sort_order'   => $request->input('sort_order', $wpFach->sort_order),
            'is_default'   => $request->boolean('is_default', false),
            'symbol_typ'   => $symbolTyp,
            'symbol_wert'  => $symbolWert,
            'symbol_farbe' => $request->input('symbol_farbe'),
        ]);

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

        // Bild löschen falls vorhanden
        if ($wpFach->symbol_typ === 'bild' && $wpFach->symbol_wert) {
            Storage::disk('public')->delete($wpFach->symbol_wert);
        }

        $wpFach->delete();

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Fach wurde gelöscht.']);
    }
}

