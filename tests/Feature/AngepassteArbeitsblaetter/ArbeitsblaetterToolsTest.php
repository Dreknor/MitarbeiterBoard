<?php

namespace Tests\Feature\AngepassteArbeitsblaetter;

use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Notifications\AngepasstesBlattOffen;
use App\Services\Arbeitsblaetter\AngepasstesBlattService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\ArbeitsblaetterTestHilfen;

/** Vite-Entries, Views ohne Bootstrap, Einstellungen, Prüfbefehl, tägliche Aufräumaufgaben, Handbuch. */
class ArbeitsblaetterToolsTest extends TestCase
{
    use ArbeitsblaetterTestHilfen;

    private const BOOTSTRAP = [
        'btn', 'btn-[a-z-]+', 'card', 'card-[a-z]+', 'form-control', 'form-group', 'form-row', 'form-check[a-z-]*',
        'custom-[a-z-]+', 'row', 'col', 'col-(?:(?:xs|sm|md|lg|xl)-)?(?:[0-9]{1,2}|auto)', 'container', 'container-fluid', 'modal', 'modal-[a-z-]+',
        'alert', 'alert-[a-z]+', 'badge', 'badge-[a-z]+', 'table', 'table-[a-z]+', 'list-group[a-z-]*',
        'd-[a-z-]+', 'pull-right', 'pull-left', 'float-right', 'float-left', 'navbar[a-z-]*', 'dropdown[a-z-]*',
        'input-group[a-z-]*', 'collapse', 'close', 'text-muted',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->arbeitsblaetterEinrichten();
    }

    public function test_vite_entries_vorhanden(): void
    {
        $vite = file_get_contents(base_path('vite.config.js'));
        $this->assertStringContainsString("'resources/css/arbeitsblaetter.css'", $vite);
        $this->assertStringContainsString("'resources/js/arbeitsblaetter.js'", $vite);
        $this->assertFileExists(resource_path('css/arbeitsblaetter.css'));
        $this->assertFileExists(resource_path('js/arbeitsblaetter.js'));
        $this->assertStringContainsString('pdfjs-dist', file_get_contents(base_path('package.json')));
    }

    public function test_views_ohne_bootstrap(): void
    {
        $muster = '/^(?:'.implode('|', self::BOOTSTRAP).')$/';
        $funde = [];
        foreach (File::allFiles(resource_path('views/arbeitsblaetter')) as $datei) {
            $inhalt = file_get_contents($datei->getPathname());
            $this->assertDoesNotMatchRegularExpression('/data-(toggle|dismiss)=|\$\(.*\)\.modal|jQuery/', $inhalt, $datei->getFilename());
            $this->assertStringContainsString('ab-', $inhalt);
            preg_match_all('/\bclass="([^"]*)"/', $inhalt, $treffer);
            foreach ($treffer[1] as $klassen) {
                foreach (preg_split('/\s+/', trim($klassen)) as $klasse) {
                    if ($klasse === '' || str_contains($klasse, ':') || str_contains($klasse, '{')) {
                        continue;
                    }
                    if (preg_match($muster, $klasse)) {
                        $funde[] = $datei->getFilename().': '.$klasse;
                    }
                }
            }
        }
        $this->assertSame([], $funde, 'Bootstrap-Klassen in Arbeitsblatt-Views gefunden');
    }

    public function test_einstellungen_speichern_und_vorschau(): void
    {
        $this->actingAs($this->lehrkraft(['edit settings']));
        $this->get(route('arbeitsblaetter.einstellungen'))->assertOk()->assertSee('KI-Kontingent');

        $this->put(route('arbeitsblaetter.einstellungen.update'), [
            'ab_ordner_vorlage' => 'AB/{gibtsnicht}', 'ab_datei_vorlage' => '{titel}',
            'ab_ki_limit_seiten' => 10, 'ab_ki_limit_euro' => '5', 'ab_ki_warnung_prozent' => 80,
        ])->assertSessionHasErrors('ab_ordner_vorlage');

        $this->put(route('arbeitsblaetter.einstellungen.update'), [
            'ab_ordner_vorlage' => 'AB/{schuljahr}/{klasse}', 'ab_datei_vorlage' => '{titel} {datum}',
            'ab_ki_limit_seiten' => 50, 'ab_ki_limit_euro' => '12,50', 'ab_ki_warnung_prozent' => 90,
            'ab_wochenplan_automatisch' => '0', 'ab_docx_ausgabe' => '1',
        ])->assertRedirect(route('arbeitsblaetter.einstellungen'));
        $this->assertSame('12.50', settings('ab_ki_limit_euro'));
        $this->assertSame('0', settings('ab_wochenplan_automatisch'));
        $this->assertSame('1', settings('ab_docx_ausgabe'));

        $this->postJson(route('arbeitsblaetter.einstellungen.vorschau'), ['ab_ordner_vorlage' => 'AB/{klasse}', 'ab_datei_vorlage' => '{nachname}'])
            ->assertOk()->assertJsonPath('beispiel', 'AB/3a/Muster.pdf');

        $this->actingAs($this->lehrkraft());
        $this->get(route('arbeitsblaetter.einstellungen'))->assertForbidden();
    }

    public function test_pruefbefehl(): void
    {
        config(['arbeitsblaetter.ki.treiber' => 'keiner']);
        $this->artisan('arbeitsblaetter:pruefen')->expectsOutputToContain('Schriften')->assertSuccessful();

        // Echte KI-Treiber mit abgelehntem Schlüssel → Exit 1
        $this->app->forgetInstance(\App\Services\Arbeitsblaetter\Ki\AbErkenner::class);
        $this->app->bind(\App\Services\Arbeitsblaetter\Ki\AbErkenner::class, fn () => \App\Services\Arbeitsblaetter\Ki\AbErkennerFabrik::erstellen());
        config(['arbeitsblaetter.ki.treiber' => 'anthropic', 'arbeitsblaetter.ki.schluessel' => 'falsch']);
        \Illuminate\Support\Facades\Http::fake(['api.anthropic.com/*' => \Illuminate\Support\Facades\Http::response(['error' => ['message' => 'invalid x-api-key']], 401)]);
        $this->artisan('arbeitsblaetter:pruefen --verbindung')->expectsOutputToContain('HTTP 401')->assertFailed();
    }

    public function test_alte_entwuerfe_werden_einmal_gemeldet_und_abbildungen_bereinigt(): void
    {
        Notification::fake();
        $user = $this->lehrkraft();
        $alt = $this->blatt(['erstellt_von' => $user->id, 'status' => AngepasstesBlatt::ZU_PRUEFEN]);
        AngepasstesBlatt::whereKey($alt->id)->update(['updated_at' => now()->subDays(100)]);
        $neu = $this->blatt(['erstellt_von' => $user->id, 'status' => AngepasstesBlatt::ZU_PRUEFEN]);

        $service = app(AngepasstesBlattService::class);
        $this->assertSame(1, $service->offeneEntwuerfeMelden());
        $this->assertSame(0, $service->offeneEntwuerfeMelden(), 'nur einmal');
        Notification::assertSentToTimes($user, AngepasstesBlattOffen::class, 1);
        $this->assertNotNull(AngepasstesBlatt::find($alt->id), 'nicht gelöscht');

        $genutzt = $service->abbildungAusInhalt($neu, $this->bildInhalt(50, 50, 'png'), 'image/png');
        $verwaist = $service->abbildungAusInhalt($neu, $this->bildInhalt(50, 50, 'png'), 'image/png');
        $neu->update(['struktur' => ['bausteine' => [['typ' => 'bild', 'media_id' => $genutzt->id]]]]);
        \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->update(['created_at' => now()->subDays(2)]);

        $this->assertSame(1, $service->verwaisteAbbildungenBereinigen());
        $this->assertNotNull($genutzt->fresh());
        $this->assertNull($verwaist->fresh());
    }

    public function test_handbuch_und_sidebar(): void
    {
        $this->assertDatabaseHas('wiki_seiten', ['slug' => 'angepasste-arbeitsblaetter', 'systemseite' => true]);

        $this->actingAs($this->lehrkraft());
        $this->get(route('arbeitsblaetter.index'))->assertOk()->assertSee('Angepasste Arbeitsblätter')->assertSee('angepasste-arbeitsblaetter', false);
    }
}
