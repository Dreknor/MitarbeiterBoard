<?php

namespace Tests\Feature\Wochenplan;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Die Wochenplan-Oberfläche kommt ohne Bootstrap aus: keine Bootstrap-Klassen und kein
 * Bootstrap-JS (data-toggle/data-dismiss, jQuery-Modals) in den Views.
 */
class OhneBootstrapTest extends TestCase
{
    private const BOOTSTRAP = [
        'btn', 'btn-[a-z-]+', 'card', 'card-[a-z]+', 'form-control', 'form-group', 'form-row', 'form-check[a-z-]*',
        'custom-[a-z-]+', 'row', 'col', 'col-(?:(?:xs|sm|md|lg|xl)-)?(?:[0-9]{1,2}|auto)', 'container', 'container-fluid', 'modal', 'modal-[a-z-]+',
        'alert', 'alert-[a-z]+', 'badge', 'badge-[a-z]+', 'table', 'table-[a-z]+', 'list-group[a-z-]*',
        'd-[a-z-]+', 'pull-right', 'pull-left', 'float-right', 'float-left', 'navbar[a-z-]*', 'dropdown[a-z-]*',
        'input-group[a-z-]*', 'collapse', 'close', 'text-muted',
        // Abstände (mt-1 …) gibt es in Bootstrap und Tailwind – hier bedient sie Tailwind (unlayered, !important).
    ];

    public function test_wochenplan_views_ohne_bootstrap(): void
    {
        $dateien = collect(File::allFiles(resource_path('views/wochenplan/new')))
            ->push(new \SplFileInfo(resource_path('views/paedDiary/partials/wochenplan.blade.php')))
            ->push(new \SplFileInfo(resource_path('views/paedDiary/partials/wochenplaene-schueler.blade.php')));

        $muster = '/^(?:'.implode('|', self::BOOTSTRAP).')$/';
        $funde = [];

        foreach ($dateien as $datei) {
            $inhalt = file_get_contents($datei->getPathname());
            $this->assertDoesNotMatchRegularExpression('/data-(toggle|dismiss)=|\$\(.*\)\.modal|jQuery/', $inhalt, $datei->getFilename());

            preg_match_all('/\bclass="([^"]*)"/', $inhalt, $treffer);
            foreach ($treffer[1] as $klassen) {
                foreach (preg_split('/\s+/', trim($klassen)) as $klasse) {
                    // Tailwind-Varianten (md:, hover: …) und Blade-Ausdrücke ignorieren
                    if ($klasse === '' || str_contains($klasse, ':') || str_contains($klasse, '{')) {
                        continue;
                    }
                    if (preg_match($muster, $klasse)) {
                        $funde[] = $datei->getFilename().': '.$klasse;
                    }
                }
            }
        }

        $this->assertSame([], $funde, 'Bootstrap-Klassen in Wochenplan-Views gefunden');
    }
}
