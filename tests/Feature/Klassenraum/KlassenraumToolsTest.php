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
            ->assertSeeInOrder(['Lautstärke', 'Aquarium', 'Sternenhimmel', 'Blumenwiese', 'Zoo', 'Vulkan', 'Zeit', 'Timer', 'Arbeitsphasen', 'Ruhe &amp; Ankommen', 'Atemübung'], false);

        foreach (array_keys(KlassenraumTools::TOOLS) as $tool) {
            $antwort->assertSee(route('klassenraum.show', $tool), false);
        }
    }

    public function test_lautstaerke_tools_zeigen_mikrofon_oberflaeche(): void
    {
        $this->actingAsWithPermission(KlassenraumTools::PERMISSION);

        foreach (['aquarium', 'sternenhimmel', 'blumenwiese', 'zoo', 'vulkan'] as $tool) {
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

    public function test_timer_bietet_alle_darstellungen(): void
    {
        $this->actingAsWithPermission(KlassenraumTools::PERMISSION);

        $antwort = $this->get(route('klassenraum.show', 'timer'))->assertOk();
        foreach (['scheibe', 'sanduhr', 'kerze', 'raupe'] as $art) {
            $antwort->assertSee('data-darstellung="'.$art.'"', false);
        }
    }

    public function test_arbeitsphasen_zeigen_planung_und_ablauf(): void
    {
        $this->actingAsWithPermission(KlassenraumTools::PERMISSION);

        $this->get(route('klassenraum.show', 'arbeitsphasen'))
            ->assertOk()
            ->assertSee('Arbeitsphasen planen')
            ->assertSee('id="abl-schritte"', false)
            ->assertSee('data-vorlage="lesen"', false)
            ->assertSee('id="abl-naechster"', false)
            ->assertSee('id="abl-zurueck"', false)
            ->assertSee('Automatisch weiter')
            ->assertSee('data-darstellung="raupe"', false)
            ->assertSee('class="krt krt-tool-arbeitsphasen krt-hell"', false);
    }

    public function test_atemuebung_zeigt_kreis_und_auswahl(): void
    {
        $this->actingAsWithPermission(KlassenraumTools::PERMISSION);

        $this->get(route('klassenraum.show', 'atemuebung'))
            ->assertOk()
            ->assertSee('id="atm-kreis"', false)
            ->assertSee('data-gruppe="rhythmus"', false)
            ->assertSee('data-gruppe="dauer"', false)
            ->assertSee('Ruhige Musik')
            ->assertSee('class="krt krt-tool-atemuebung krt-dunkel"', false)
            ->assertDontSee('id="krt-mikro"', false);
    }

    public function test_uebersicht_zeigt_vorschaubilder(): void
    {
        $this->actingAsWithPermission(KlassenraumTools::PERMISSION);

        $antwort = $this->get(route('klassenraum.index'))->assertOk();
        foreach (KlassenraumTools::TOOLS as $tool) {
            $this->assertFileExists(public_path($tool['vorschau']));
            $antwort->assertSee(asset($tool['vorschau']), false);
        }
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
