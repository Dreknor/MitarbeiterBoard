<?php

namespace Tests\Feature\Klassenraum;

use App\Support\KlassenraumTools;
use Tests\TestCase;

class KlassenraumToolsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_gaeste_werden_zum_login_geleitet(): void
    {
        $this->get(route('klassenraum.index'))->assertRedirect(route('login'));
        $this->get(route('klassenraum.show', 'aquarium'))->assertRedirect(route('login'));
    }

    public function test_ohne_recht_kein_zugriff(): void
    {
        $this->actingAsWithPermission('view wiki');

        $this->get(route('klassenraum.index'))->assertForbidden();

        foreach (array_keys(KlassenraumTools::TOOLS) as $tool) {
            $this->get(route('klassenraum.show', $tool))->assertForbidden();
        }
    }

    public function test_uebersicht_listet_alle_tools_nach_kategorie(): void
    {
        $this->actingAsWithPermission(KlassenraumTools::PERMISSION);

        $antwort = $this->get(route('klassenraum.index'))
            ->assertOk()
            ->assertSee('Klassenraum-Tools')
            ->assertSeeInOrder(['Lautstärke', 'Aquarium', 'Sternenhimmel', 'Blumenwiese', 'Zeit', 'Timer']);

        foreach (array_keys(KlassenraumTools::TOOLS) as $tool) {
            $antwort->assertSee(route('klassenraum.show', $tool), false);
        }
    }

    public function test_lautstaerke_tools_zeigen_mikrofon_oberflaeche(): void
    {
        $this->actingAsWithPermission(KlassenraumTools::PERMISSION);

        foreach (['aquarium', 'sternenhimmel', 'blumenwiese'] as $tool) {
            $this->get(route('klassenraum.show', $tool))
                ->assertOk()
                ->assertSee(KlassenraumTools::TOOLS[$tool]['titel'])
                ->assertSee('id="krt-szene"', false)
                ->assertSee('id="krt-mikro"', false)
                ->assertSee('Raumlautstärke')
                ->assertSee('nichts aufgenommen, gespeichert oder übertragen')
                ->assertSee(route('klassenraum.index'), false)
                // eigenständige Vollbildseite ohne App-Navigation
                ->assertDontSee('tw-sidebar', false);
        }
    }

    public function test_timer_zeigt_eigene_oberflaeche_ohne_mikrofon(): void
    {
        $this->actingAsWithPermission(KlassenraumTools::PERMISSION);

        $this->get(route('klassenraum.show', 'timer'))
            ->assertOk()
            ->assertSee('id="tmr-zeit"', false)
            ->assertSee('data-minuten="5"', false)
            ->assertSee('Gong am Ende')
            // Fokusmodus: kompakte Steuerung und ruhige Minutenanzeige
            ->assertSee('id="tmr-fokusleiste"', false)
            ->assertSee('Sekunden anzeigen')
            ->assertDontSee('id="krt-mikro"', false);
    }

    public function test_unbekanntes_tool_liefert_404(): void
    {
        $this->actingAsWithPermission(KlassenraumTools::PERMISSION);

        $this->get('/klassenraum/gibt-es-nicht')->assertNotFound();
    }

    public function test_menue_eintrag_nur_mit_recht(): void
    {
        $link = 'href="'.route('klassenraum.index').'"';

        $this->actingAs($this->createUserWithPermission(KlassenraumTools::PERMISSION))
            ->get(route('benachrichtigungen.index'))
            ->assertOk()
            ->assertSee($link, false)
            ->assertSee('Klassenraum-Tools');

        $this->actingAs($this->createUserWithPermission('view wiki'))
            ->get(route('benachrichtigungen.index'))
            ->assertOk()
            ->assertDontSee($link, false);
    }

    public function test_jedes_tool_hat_vite_entry_und_bekannte_kategorie(): void
    {
        $viteConfig = file_get_contents(base_path('vite.config.js'));

        foreach (KlassenraumTools::TOOLS as $schluessel => $tool) {
            $this->assertArrayHasKey($tool['kategorie'], KlassenraumTools::KATEGORIEN, $schluessel);
            $this->assertFileExists(base_path($tool['entry']));
            $this->assertStringContainsString("'{$tool['entry']}'", $viteConfig, "{$schluessel} fehlt in vite.config.js");

            if (! $tool['mikrofon']) {
                $this->assertFileExists(resource_path("views/klassenraum/tools/{$schluessel}.blade.php"));
            }
        }
    }

    public function test_migration_legt_recht_an(): void
    {
        $this->assertDatabaseHas('permissions', ['name' => KlassenraumTools::PERMISSION, 'guard_name' => 'web']);
    }
}
