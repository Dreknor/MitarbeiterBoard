<?php

namespace Tests\Feature\Personal;

use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;
use Tests\Feature\Personal\Zeit\ZeitTestHelpers;
use Tests\TestCase;

/**
 * Einführungstour (<x-tour>) für Eigene Daten → Mein Profil → Urlaub → Arbeitszeitnachweis
 */
class EinfuehrungsTourTest extends TestCase
{
    use ZeitTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->neuesModell();
        Carbon::setTestNow('2026-10-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Liest die Tour-Konfiguration aus dem gerenderten HTML */
    private function tour(string $html, string $id): ?array
    {
        preg_match_all('#<script type="application/json" data-tour-config>(.*?)</script>#s', $html, $treffer);
        foreach ($treffer[1] as $json) {
            $config = json_decode($json, true);
            if (($config['id'] ?? null) === $id) {
                return $config;
            }
        }

        return null;
    }

    public function test_komponente_rendert_konfiguration_und_filtert_leere_schritte(): void
    {
        $user = $this->mitarbeiter();
        $this->actingAs($user);

        $html = Blade::render('<x-tour id="demo" :version="2" :steps="$steps" />', [
            'steps' => [['title' => 'A </script> & "B"', 'text' => 'x'], null, ['target' => 'ziel', 'title' => 'C', 'text' => 'y']],
        ]);

        $config = $this->tour($html, 'demo');
        $this->assertNotNull($config);
        $this->assertSame($user->id, $config['user']);
        $this->assertSame(2, $config['version']);
        $this->assertCount(2, $config['steps']);
        $this->assertSame('A </script> & "B"', $config['steps'][0]['title']);
        $this->assertStringNotContainsString('A </script>', $html);
    }

    public function test_touren_verketten_eigene_daten_profil_urlaub_arbeitszeit(): void
    {
        $user = $this->mitarbeiter();
        $this->actingAs($user);

        $eigeneDaten = $this->get(route('employes.self'))->assertOk()->assertSee('data-tour-start="eigene-daten"', false);
        $this->assertStringContainsString('tour=mein-profil', $this->tour($eigeneDaten->getContent(), 'eigene-daten')['next']['url']);

        $profil = $this->get(route('self-service.index'))->assertOk()->assertSee('data-tour="tab-urlaub"', false);
        $this->assertStringContainsString('tour=urlaub', $this->tour($profil->getContent(), 'mein-profil')['next']['url']);

        $urlaub = $this->get(route('holidays.index'))->assertOk()->assertSee('data-tour="urlaub-antrag"', false);
        $this->assertStringContainsString('tour=arbeitszeit', $this->tour($urlaub->getContent(), 'urlaub')['next']['url']);

        $nachweis = $this->get(route('timesheets.show', $user->id))->assertOk()->assertSee('data-tour="az-tage"', false);
        $config = $this->tour($nachweis->getContent(), 'arbeitszeit');
        $this->assertNotNull($config);
        $this->assertNull($config['next']);
    }

    public function test_ohne_urlaubsrecht_fuehrt_profil_direkt_zum_arbeitszeitnachweis(): void
    {
        $user = $this->mitarbeiter(['has timesheet']);
        $this->actingAs($user);

        $profil = $this->get(route('self-service.index'))->assertOk();
        $this->assertStringContainsString('tour=arbeitszeit', $this->tour($profil->getContent(), 'mein-profil')['next']['url']);
    }

    public function test_fremder_nachweis_zeigt_keine_tour(): void
    {
        $chef = $this->mitarbeiter(['has timesheet', 'lock timesheets', 'edit employe']);
        $ma = $this->mitarbeiter();
        $ma->update(['superior_id' => $chef->id]);

        $antwort = $this->actingAs($chef)->get(route('timesheets.show', $ma->id))->assertOk();
        $this->assertNull($this->tour($antwort->getContent(), 'arbeitszeit'));
    }
}
