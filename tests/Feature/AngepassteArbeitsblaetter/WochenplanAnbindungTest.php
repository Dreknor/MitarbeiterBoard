<?php

namespace Tests\Feature\AngepassteArbeitsblaetter;

use App\Models\Arbeitsblaetter\AbFassung;
use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\Wochenplan\WpDatei;
use App\Models\Wochenplan\WpPlan;
use App\Notifications\AngepasstesBlattZurPruefung;
use App\Services\Arbeitsblaetter\AbWochenplanService;
use App\Services\Arbeitsblaetter\AngepasstesBlattService;
use App\Services\Arbeitsblaetter\SehprofilService;
use App\Services\Wochenplan\WochenplanService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\ArbeitsblaetterTestHilfen;
use Tests\Traits\WochenplanTestHilfen;

/**
 * Wochenplan-Anbindung: Upload im Klassenplan → Erkennung nur bei Profilkindern; Freigabe →
 * Kinderplan angelegt + Fassung ersetzt Original in alleDateien(); Original löschen → Fassungen
 * nach _gelöscht/; neuer Kinderplan zieht Fassungen nach; Kinderplan/Vorlage lösen nichts aus.
 */
class WochenplanAnbindungTest extends TestCase
{
    use ArbeitsblaetterTestHilfen, WochenplanTestHilfen;

    private Klasse $klasse;

    protected function setUp(): void
    {
        parent::setUp();
        // Mitten in der Woche: am Wochenende ist die Woche der Test-Klassenpläne (Mo–Fr) schon vorbei
        $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(10, 0));
        $this->arbeitsblaetterEinrichten();
        $this->nextcloudFake(true);
        $this->klasse = Klasse::factory()->create(['name' => '3a']);
    }

    private function lehrerIn()
    {
        $user = $this->abBenachrichtigungenAn($this->paedagoge([$this->klasse], ['create wochenplan', 'view wochenplan', 'create adapted worksheets']));
        $this->actingAs($user);

        return $user;
    }

    private function hochladen(WpPlan $plan, string $name = 'Brüche.jpg'): WpDatei
    {
        $this->post(route('wp.dateien.store', $plan), ['files' => [UploadedFile::fake()->createWithContent($name, $this->bildInhalt())]], ['Accept' => 'application/json'])->assertOk();

        return WpDatei::where('wp_plan_id', $plan->id)->latest('id')->firstOrFail();
    }

    public function test_upload_im_klassenplan_startet_erkennung_nur_bei_profilkindern(): void
    {
        Notification::fake();
        $user = $this->lehrerIn();
        $plan = $this->klassenplan($this->klasse);

        $this->hochladen($plan, 'ohne-profil.jpg');
        $this->assertSame(0, AngepasstesBlatt::count(), 'Klasse ohne Profilkinder → nichts');

        $kind = $this->kindMitProfil($this->klasse);
        $datei = $this->hochladen($plan);
        $blatt = AngepasstesBlatt::firstOrFail();

        $this->assertSame('wochenplan', $blatt->quelle);
        $this->assertSame($datei->id, $blatt->quelle_wp_datei_id);
        $this->assertSame(3, $blatt->klassenstufe);
        $this->assertSame(AngepasstesBlatt::ZU_PRUEFEN, $blatt->status);
        Notification::assertSentTo($user, AngepasstesBlattZurPruefung::class);

        // Abzeichen im Editor
        $this->getJson(route('wp.edit', $plan))->assertOk();
        $info = app(AbWochenplanService::class)->dateiInfos($plan);
        $this->assertSame('zu_pruefen', $info[$datei->id]['status']);
        $this->assertNotNull($kind);
    }

    public function test_automatik_aus_kinderplan_und_vorlage_loesen_nichts_aus(): void
    {
        $this->lehrerIn();
        $kind = $this->kindMitProfil($this->klasse);
        $klassenplan = $this->klassenplan($this->klasse);

        $this->abEinstellung('ab_wochenplan_automatisch', '0');
        $this->hochladen($klassenplan);
        $this->assertSame(0, AngepasstesBlatt::count());
        $this->abEinstellung('ab_wochenplan_automatisch', '1');

        $kinderplan = app(WochenplanService::class)->kinderplanErstellen($klassenplan, $kind);
        $this->hochladen($kinderplan);
        $vorlage = $this->klassenplan($this->klasse, ['is_vorlage' => true, 'klasse_id' => null]);
        app(\App\Services\Wochenplan\WpAblageService::class)->dateiSpeichern($vorlage, UploadedFile::fake()->createWithContent('v.jpg', $this->bildInhalt()), null);
        $this->assertSame(0, AngepasstesBlatt::count());
    }

    public function test_freigabe_legt_kinderplan_an_und_fassung_ersetzt_original(): void
    {
        $user = $this->lehrerIn();
        $kind = $this->kindMitProfil($this->klasse);
        $ohneProfil = Schueler::factory()->create(['klasse_id' => $this->klasse->id]);
        $klassenplan = $this->klassenplan($this->klasse);
        $original = $this->hochladen($klassenplan);
        $blatt = AngepasstesBlatt::firstOrFail();

        $this->postJson(route('arbeitsblaetter.freigeben', $blatt), ['version' => $blatt->version])->assertOk();

        $kinderplan = WpPlan::where('parent_plan_id', $klassenplan->id)->where('schueler_id', $kind->id)->firstOrFail();
        $this->assertFalse(WpPlan::where('schueler_id', $ohneProfil->id)->exists());

        $fassungDatei = WpDatei::where('wp_plan_id', $kinderplan->id)->firstOrFail();
        $this->assertSame($original->id, $fassungDatei->ersetzt_wp_datei_id);
        $this->assertSame('Brüche (angepasst).pdf', $fassungDatei->name);
        $fassung = AbFassung::findOrFail($fassungDatei->ab_fassung_id);
        $this->assertSame($fassungDatei->id, $fassung->wp_datei_id);

        $alle = $kinderplan->fresh()->alleDateien();
        $this->assertSame([$fassungDatei->id], $alle->pluck('id')->all(), 'Original im Kinderplan ausgeblendet');
        $json = app(WochenplanService::class)->toArray($kinderplan->fresh(), $user);
        $this->assertTrue($json['dateien'][0]['angepasst']);
        $this->assertSame($original->id, $json['dateien'][0]['original_id']);
        $this->assertSame([$original->id], $klassenplan->fresh()->alleDateien()->pluck('id')->all(), 'Klassenplan unverändert');

        // Erneute Freigabe ohne Änderung → keine doppelte Datei
        app(AbWochenplanService::class)->fassungenVerteilen($blatt->fresh());
        $this->assertSame(1, WpDatei::where('wp_plan_id', $kinderplan->id)->count());

        // Struktur geändert + erneut freigegeben → Fassung ersetzt
        app(AngepasstesBlattService::class)->speichern($blatt->fresh(), ['bausteine' => [['typ' => 'text', 'text' => 'Neu']]], $blatt->fresh()->version);
        app(AngepasstesBlattService::class)->freigeben($blatt->fresh(), $user);
        $dateien = WpDatei::where('wp_plan_id', $kinderplan->id)->get();
        $this->assertCount(1, $dateien);
        $this->assertNotSame($fassungDatei->id, $dateien->first()->id);
    }

    public function test_original_loeschen_entfernt_fassungen_nach_geloescht(): void
    {
        $user = $this->lehrerIn();
        $kind = $this->kindMitProfil($this->klasse);
        $klassenplan = $this->klassenplan($this->klasse);
        $original = $this->hochladen($klassenplan);
        $blatt = AngepasstesBlatt::firstOrFail();
        app(AngepasstesBlattService::class)->freigeben($blatt, $user);
        $fassungDatei = WpDatei::whereNotNull('ersetzt_wp_datei_id')->firstOrFail();
        $pfad = $fassungDatei->fresh()->nextcloud_pfad;
        $this->assertArrayHasKey($pfad, $this->nextcloud->dateien);

        $this->deleteJson(route('wp.dateien.destroy', [$klassenplan, $original]))->assertOk();

        $this->assertNull(WpDatei::find($fassungDatei->id));
        $this->assertArrayNotHasKey($pfad, $this->nextcloud->dateien);
        $this->assertNotEmpty(array_filter(array_keys($this->nextcloud->dateien), fn ($p) => str_starts_with($p, '_gelöscht/') && str_contains($p, 'angepasst')));
        $this->assertNotNull(AngepasstesBlatt::find($blatt->id), 'Blatt bleibt in der Bibliothek');
        $this->assertNotNull($kind);
    }

    public function test_spaeter_angelegter_kinderplan_und_neues_profil_ziehen_nach(): void
    {
        $user = $this->lehrerIn();
        $erstes = $this->kindMitProfil($this->klasse);
        $klassenplan = $this->klassenplan($this->klasse);
        $original = $this->hochladen($klassenplan);
        app(AngepasstesBlattService::class)->freigeben(AngepasstesBlatt::firstOrFail(), $user);

        // Kind mit Profil, dessen Kinderplan erst später angelegt wird (Profil ohne Nachziehen anlegen)
        $zweites = Schueler::factory()->create(['klasse_id' => $this->klasse->id]);
        \App\Models\Arbeitsblaetter\Sehprofil::create(['schueler_id' => $zweites->id, 'werte' => ['groesse' => 30], 'aktiv' => true]);
        $kinderplan = app(WochenplanService::class)->kinderplanErstellen($klassenplan, $zweites);
        $this->assertSame(1, WpDatei::where('wp_plan_id', $kinderplan->id)->where('ersetzt_wp_datei_id', $original->id)->count());

        // Neues Profil → Fassungen für laufende Woche nachziehen (Kinderplan wird angelegt)
        $drittes = Schueler::factory()->create(['klasse_id' => $this->klasse->id]);
        app(SehprofilService::class)->speichern($drittes, null, ['groesse' => 26], true, $user);
        $plan3 = WpPlan::where('schueler_id', $drittes->id)->firstOrFail();
        $alt = WpDatei::where('wp_plan_id', $plan3->id)->firstOrFail();

        // Profil geändert → Fassung wird neu gesetzt und ersetzt
        app(SehprofilService::class)->speichern($drittes, null, ['groesse' => 40], true, $user);
        $neu = WpDatei::where('wp_plan_id', $plan3->id)->get();
        $this->assertCount(1, $neu);
        $this->assertNotSame($alt->id, $neu->first()->id);
        $this->assertSame(40, AbFassung::find($neu->first()->ab_fassung_id)->profil_werte['groesse']);
        $this->assertNotNull($erstes);
    }

    public function test_knopf_im_wochenplan_editor(): void
    {
        $this->lehrerIn();
        $klassenplan = $this->klassenplan($this->klasse);
        $datei = $this->hochladen($klassenplan); // Klasse ohne Profilkinder → keine Automatik
        $this->assertSame(0, AngepasstesBlatt::count());

        $antwort = $this->postJson(route('wp.dateien.anpassen', [$klassenplan, $datei]))->assertOk();
        $blatt = AngepasstesBlatt::firstOrFail();
        $antwort->assertJsonPath('url', route('arbeitsblaetter.edit', $blatt));
        $this->assertSame('zu_pruefen', $antwort->json('plan.arbeitsblaetter.dateien.'.$datei->id.'.status'));

        // ohne Recht für Angepasste Arbeitsblätter
        $this->actingAs($this->paedagoge([$this->klasse]));
        $this->postJson(route('wp.dateien.anpassen', [$klassenplan, $datei]))->assertForbidden();
    }
}
