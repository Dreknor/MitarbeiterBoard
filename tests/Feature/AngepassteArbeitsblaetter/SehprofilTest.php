<?php

namespace Tests\Feature\AngepassteArbeitsblaetter;

use App\Models\Arbeitsblaetter\AbFassung;
use App\Models\Arbeitsblaetter\AbProfilVorlage;
use App\Models\Arbeitsblaetter\Sehprofil;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Services\Arbeitsblaetter\AbAusgabeService;
use App\Services\Arbeitsblaetter\SehprofilService;
use App\Support\AbProfil;
use Tests\TestCase;
use Tests\Traits\ArbeitsblaetterTestHilfen;

/** Sehprofile: Rechte, Vorlage + Abweichungen, keine Fremdfelder, Hash-Wechsel → Fassungen veraltet. */
class SehprofilTest extends TestCase
{
    use ArbeitsblaetterTestHilfen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->arbeitsblaetterEinrichten();
    }

    public function test_vorlagen_sind_angelegt(): void
    {
        $this->assertSame(['Groß', 'Sehr groß', 'Kontrast (invertiert)', 'A3 groß'], AbProfilVorlage::orderBy('sortierung')->pluck('name')->all());
        $this->assertSame('A3', AbProfilVorlage::where('name', 'A3 groß')->first()->wirksameWerte()['format']);
    }

    public function test_nur_abweichungen_werden_gespeichert_und_fremdfelder_verworfen(): void
    {
        $klasse = Klasse::factory()->create();
        $kind = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $vorlage = AbProfilVorlage::where('name', 'Sehr groß')->first();
        $this->actingAs($this->lehrkraft(['manage visual profiles']));

        $werte = array_merge($vorlage->wirksameWerte(), ['farbschema' => 'gelb', 'groesse' => 999, 'visus' => '0,1', 'diagnose' => 'xy']);
        $this->putJson(route('arbeitsblaetter.profile.speichern', $kind), ['vorlage_id' => $vorlage->id, 'aktiv' => true, 'werte' => $werte])
            ->assertOk()->assertJsonPath('profil.werte.groesse', 48);

        $profil = Sehprofil::where('schueler_id', $kind->id)->first();
        $this->assertSame(['groesse' => 48, 'farbschema' => 'gelb'], $profil->werte);
        $this->assertArrayNotHasKey('visus', $profil->wirksameWerte());
        $this->assertTrue($profil->wirksameWerte()['fett'], 'Wert der Vorlage bleibt wirksam');
    }

    public function test_rechte(): void
    {
        $klasse = Klasse::factory()->create();
        $kind = Schueler::factory()->create(['klasse_id' => $klasse->id]);

        $this->actingAs($this->lehrkraft(['create adapted worksheets']));
        $this->get(route('arbeitsblaetter.profile'))->assertOk();
        $this->putJson(route('arbeitsblaetter.profile.speichern', $kind), ['aktiv' => true, 'werte' => []])->assertForbidden();
        $this->postJson(route('arbeitsblaetter.profile.vorlagen.store'), ['name' => 'X', 'werte' => []])->assertForbidden();

        $this->actingAs($this->lehrkraft(['view wiki']));
        $this->get(route('arbeitsblaetter.profile'))->assertForbidden();

        $this->actingAs($this->lehrkraft(['manage visual profiles']));
        $this->get(route('arbeitsblaetter.profile', ['klasse' => $klasse->id]))->assertOk()->assertSee($kind->vorname);
        $this->post(route('arbeitsblaetter.profile.vorschau'), ['werte' => ['groesse' => 30]])->assertOk()->assertSee('font-size: 30pt', false);
    }

    public function test_hash_wechsel_macht_fassungen_veraltet(): void
    {
        $kind = $this->kindMitProfil();
        $blatt = $this->blatt();
        $fassung = app(AbAusgabeService::class)->fassung($blatt, $kind);
        $this->assertSame(AbFassung::AKTUELL, $fassung->status);

        $service = app(SehprofilService::class);
        $profil = $service->profilFuer($kind);
        $service->speichern($kind, $profil->vorlage_id, $profil->wirksameWerte(), true, null);
        $this->assertSame(AbFassung::AKTUELL, $fassung->fresh()->status, 'unverändert → bleibt aktuell');

        $service->speichern($kind, $profil->vorlage_id, array_merge($profil->wirksameWerte(), ['groesse' => 36]), true, null);
        $this->assertSame(AbFassung::VERALTET, $fassung->fresh()->status);
    }

    public function test_vorlage_aendern_wirkt_auf_kinder(): void
    {
        $kind = $this->kindMitProfil(null, [], 'Groß');
        $blatt = $this->blatt();
        $fassung = app(AbAusgabeService::class)->fassung($blatt, $kind);

        $vorlage = AbProfilVorlage::where('name', 'Groß')->first();
        app(SehprofilService::class)->vorlageSpeichern($vorlage, 'Groß', array_merge($vorlage->wirksameWerte(), ['groesse' => 22]));

        $this->assertSame(22, app(SehprofilService::class)->wirksameWerte($kind)['groesse']);
        $this->assertSame(AbFassung::VERALTET, $fassung->fresh()->status);
        $this->assertNotNull(app(SehprofilService::class)->vorlageLoeschen($vorlage), 'genutzte Vorlage nicht löschbar');
    }

    public function test_grenzen_der_werte(): void
    {
        $w = AbProfil::normalisieren(['groesse' => 5, 'zeilenabstand' => 9, 'schrift' => 'comic', 'format' => 'A5', 'fett' => 'ja']);
        $this->assertSame(14, $w['groesse']);
        $this->assertSame(2.5, $w['zeilenabstand']);
        $this->assertSame('atkinson', $w['schrift']);
        $this->assertSame('A4', $w['format']);
        $this->assertTrue($w['fett']);
        $this->assertSame(AbProfil::hash(['groesse' => 20]), AbProfil::hash(AbProfil::standard()));
    }
}
