<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wochenplan\WpFormatvorlageRequest;
use App\Models\Wochenplan\WpFormatvorlage;
use App\Models\Wochenplan\WpPlan;
use Illuminate\Http\Request;

class WpFormatvorlageController extends Controller
{
    public function index()
    {
        $formatvorlagen = WpFormatvorlage::withCount('plaene')->orderBy('name')->get();
        return view('wochenplan.new.formatvorlagen.index', compact('formatvorlagen'));
    }

    public function create()
    {
        return view('wochenplan.new.formatvorlagen.create');
    }

    public function store(WpFormatvorlageRequest $request)
    {
        $layoutConfig = $this->buildLayoutConfig($request);

        if ($request->boolean('is_default')) {
            WpFormatvorlage::where('is_default', true)->update(['is_default' => false]);
        }

        WpFormatvorlage::create([
            'name'           => $request->input('name'),
            'beschreibung'   => $request->input('beschreibung'),
            'schriftgroesse' => $request->input('schriftgroesse', 'normal'),
            'schriftart'     => $request->input('schriftart', 'Arial, sans-serif'),
            'layout_config'  => $layoutConfig,
            'blade_template' => $request->input('blade_template', 'wochenplan.pdf.standard'),
            'is_default'     => $request->boolean('is_default'),
            'created_by'     => auth()->id(),
        ]);

        return redirect()->route('wp.formatvorlagen.index')
            ->with(['type' => 'success', 'Meldung' => 'Formatvorlage wurde erstellt.']);
    }

    public function edit(WpFormatvorlage $wpFormatvorlage)
    {
        return view('wochenplan.new.formatvorlagen.edit', compact('wpFormatvorlage'));
    }

    public function update(WpFormatvorlageRequest $request, WpFormatvorlage $wpFormatvorlage)
    {
        $layoutConfig = $this->buildLayoutConfig($request);

        if ($request->boolean('is_default')) {
            WpFormatvorlage::where('is_default', true)
                ->where('id', '!=', $wpFormatvorlage->id)
                ->update(['is_default' => false]);
        }

        $wpFormatvorlage->update([
            'name'           => $request->input('name'),
            'beschreibung'   => $request->input('beschreibung'),
            'schriftgroesse' => $request->input('schriftgroesse', 'normal'),
            'schriftart'     => $request->input('schriftart', 'Arial, sans-serif'),
            'layout_config'  => $layoutConfig,
            'blade_template' => $request->input('blade_template', 'wochenplan.pdf.standard'),
            'is_default'     => $request->boolean('is_default'),
        ]);

        return redirect()->route('wp.formatvorlagen.index')
            ->with(['type' => 'success', 'Meldung' => 'Formatvorlage wurde gespeichert.']);
    }

    public function destroy(WpFormatvorlage $wpFormatvorlage)
    {
        if ($wpFormatvorlage->plaene()->exists()) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Formatvorlage wird in Plaenen verwendet und kann nicht geloescht werden.',
            ]);
        }

        $wpFormatvorlage->delete();

        return redirect()->route('wp.formatvorlagen.index')
            ->with(['type' => 'success', 'Meldung' => 'Formatvorlage wurde geloescht.']);
    }

    public function vorschau(WpFormatvorlage $wpFormatvorlage)
    {
        $plan = $this->dummyPlan();

        $template = $wpFormatvorlage->blade_template ?? 'wochenplan.pdf.standard';

        return view($template, [
            'plan'          => $plan,
            'formatvorlage' => $wpFormatvorlage,
            'config'        => $wpFormatvorlage->layout_config ?? [],
            'vorschau'      => true,
        ]);
    }

    /**
     * Vorschau-HTML für den Live-Editor (Ajax-Endpunkt).
     */
    public function vorschauHtml(Request $request)
    {
        // Der Request enthält Formularfelder flach (name, schriftgroesse, col_fach …).
        // buildLayoutConfig erwartet einen Request, also übergeben wir ihn direkt.
        $configData = $this->buildLayoutConfig($request);

        $fv = new WpFormatvorlage([
            'name'           => 'Vorschau',
            'schriftgroesse' => $request->input('schriftgroesse', 'normal'),
            'schriftart'     => $request->input('schriftart', 'Arial, sans-serif'),
            'layout_config'  => $configData,
            'blade_template' => $request->input('blade_template', 'wochenplan.pdf.standard'),
        ]);

        $plan     = $this->dummyPlan();
        $template = $fv->blade_template ?? 'wochenplan.pdf.standard';

        return view($template, [
            'plan'          => $plan,
            'formatvorlage' => $fv,
            'config'        => $configData,
            'vorschau'      => true,
        ]);
    }

    /**
     * Erzeugt einen Dummy-Plan mit Beispiel-Fächern und Aufgaben für die Vorschau.
     */
    private function dummyPlan(): WpPlan
    {
        $plan = new WpPlan([
            'name'                => 'Beispiel-Wochenplan',
            'gueltig_von'         => now(),
            'gueltig_bis'         => now()->addDays(4),
            'selbsteinschaetzung' => 1,
            'klasse_id'           => null,
            'schueler_id'         => null,
        ]);

        // Dummy-Fächer mit Aufgaben als Plain-Objekte
        $faecher = collect([
            ['name' => 'Deutsch',  'symbol' => '📖', 'aufgaben' => ['Seite 12 lesen', 'Diktat üben', 'Aufsatz schreiben']],
            ['name' => 'Mathe',    'symbol' => '🔢', 'aufgaben' => ['Aufgaben S. 34',  '10 Rechenaufgaben']],
            ['name' => 'Sachkunde','symbol' => '🌍', 'aufgaben' => ['Referat vorbereiten']],
        ])->map(function ($data) {
            $aufgaben = collect($data['aufgaben'])->map(fn($text) => (object)[
                'aufgabe' => $text,
                'dauer'   => '',
            ]);

            $fach = (object)[
                'name'            => $data['name'],
                'symbol_html'     => '<span style="font-size:1.1em;margin-right:3px">' . $data['symbol'] . '</span>',
                'pdf_symbol_html' => '<span style="font-size:1.1em;margin-right:3px;font-family:\'NotoSymbols\',Arial,sans-serif;">' . $data['symbol'] . '</span>',
            ];

            return (object)[
                'display_name' => $data['name'],
                'fach'         => $fach,
                'aufgaben'     => $aufgaben,
            ];
        });

        // planFaecher als eager-geladene Relation simulieren
        $plan->setRelation('planFaecher', $faecher);

        return $plan;
    }

    private function buildLayoutConfig($request): array
    {
        return [
            'papier' => [
                'groesse'     => $request->input('papier_groesse', 'A4'),
                'ausrichtung' => $request->input('papier_ausrichtung', 'portrait'),
            ],
            'seitenraender' => [
                'oben'   => (int) $request->input('margin_top',    20),
                'unten'  => (int) $request->input('margin_bottom', 20),
                'links'  => (int) $request->input('margin_left',   15),
                'rechts' => (int) $request->input('margin_right',  15),
            ],
            'abstände' => [
                'zwischen_fächern'    => (int) $request->input('abstand_faecher',   5),
                'zwischen_aufgaben'   => (int) $request->input('abstand_aufgaben',  2),
                'min_fach_zeilenhoehe'=> (int) $request->input('min_zeilenhoehe',   0),
            ],
            'typografie' => [
                'schriftgroesse_pt' => (int) $request->input('schriftgroesse_pt', 11),
                'zeilenabstand'     => (float) $request->input('zeilenabstand', 1.4),
            ],
            'spalten' => [
                'fach'                      => $request->input('col_fach',     '15') . '%',
                'aufgaben'                  => $request->input('col_aufgaben', '55') . '%',
                'check'                     => $request->input('col_check',    '5')  . '%',
                'unterschrift'              => $request->input('col_unterschrift', '25') . '%',
                'zeige_dauer'               => $request->boolean('zeige_dauer_spalte', false),
                'zeige_check_spalte'        => $request->boolean('zeige_check_spalte', true),
                'zeige_unterschrift_spalte' => $request->boolean('zeige_unterschrift_spalte', true),
                'zeige_kontrolliert_spalte' => $request->boolean('zeige_kontrolliert_spalte', false),
            ],
            'header' => [
                'zeige_name_feld' => $request->boolean('zeige_name_feld',  true),
                'zeige_klasse'    => $request->boolean('zeige_klasse',     true),
                'zeige_zeitraum'  => $request->boolean('zeige_zeitraum',   true),
                'zeige_logo'      => $request->boolean('zeige_logo',       false),
                'logo_pfad'       => $request->input('logo_pfad'),
                'freitext'        => $request->input('header_freitext'),
            ],
            'footer' => [
                'zeige_selbsteinschaetzung' => $request->boolean('zeige_selbsteinschaetzung', true),
                'freitext'                  => $request->input('footer_freitext'),
            ],
        ];
    }
}
