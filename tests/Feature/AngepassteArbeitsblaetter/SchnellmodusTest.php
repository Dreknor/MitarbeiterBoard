<?php

namespace Tests\Feature\AngepassteArbeitsblaetter;

use App\Models\Arbeitsblaetter\AbFassung;
use App\Models\Arbeitsblaetter\AbProfilVorlage;
use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Notifications\AngepasstesBlattFehler;
use App\Notifications\AngepasstesBlattZurPruefung;
use App\Services\Arbeitsblaetter\Ki\AbErkennungFehler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\ArbeitsblaetterTestHilfen;

/**
 * Schnellmodus (hochladen → fertige Fassungen) und Arbeitsblatt-Benachrichtigungen als Opt-in.
 */
class SchnellmodusTest extends TestCase
{
    use ArbeitsblaetterTestHilfen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->arbeitsblaetterEinrichten();
    }

    public function test_erfassung_zeigt_schnell_und_erweitert_mit_zielen(): void
    {
        $kind = $this->kindMitProfil();
        $this->actingAs($this->lehrkraft());

        $this->get(route('arbeitsblaetter.create'))->assertOk()
            ->assertSee('Hochladen – fertiges Blatt')
            ->assertSee('Prüfen und bearbeiten im Editor')
            ->assertSee($kind->vorname.' '.$kind->nachname)
            ->assertSee('Ohne Kind – nach Profil-Vorlage');
    }

    public function test_schnellmodus_von_upload_bis_fassung(): void
    {
        Notification::fake();
        $kind = $this->kindMitProfil();
        $vorlage = AbProfilVorlage::where('name', 'A3 groß')->firstOrFail();
        $user = $this->lehrkraft();
        $this->actingAs($user);

        $antwort = $this->postJson(route('arbeitsblaetter.schnell.store'), [
            'dateien' => [UploadedFile::fake()->createWithContent('zoo.jpg', $this->bildInhalt())],
            'kinder' => [$kind->id, 99999],
            'vorlagen' => [$vorlage->id],
        ])->assertOk();

        $blatt = AngepasstesBlatt::firstOrFail();
        $antwort->assertJson(['url' => route('arbeitsblaetter.schnell', $blatt)]);
        $this->assertSame(['kinder' => [$kind->id], 'vorlagen' => [$vorlage->id]], $blatt->schnell_ziele, 'fremde/unbekannte Kinder fallen weg');
        $this->assertSame(AngepasstesBlatt::ZU_PRUEFEN, $blatt->status);
        $this->assertSame('Brüche', $blatt->titel, 'Titel aus der erkannten Überschrift');

        $this->get(route('arbeitsblaetter.schnell', $blatt))->assertOk()->assertSee('Schnellmodus');

        $fertig = $this->postJson(route('arbeitsblaetter.schnell.fertig', $blatt), ['version' => $blatt->version])
            ->assertOk()->assertJsonCount(2, 'fassungen')->json();

        $blatt->refresh();
        $this->assertSame(AngepasstesBlatt::FREIGEGEBEN, $blatt->status);
        $this->assertSame($user->id, $blatt->geprueft_von);
        $this->assertSame(2, AbFassung::where('blatt_id', $blatt->id)->count());
        $this->get($fertig['fassungen'][0]['pdf'])->assertOk()->assertHeader('Content-Disposition');
        $this->assertStringStartsWith('inline;', $this->get($fertig['fassungen'][0]['ansehen'])->headers->get('Content-Disposition'));

        // erneut aufrufen (z. B. Seite neu geladen) → dieselben Fassungen, nichts doppelt
        $this->get(route('arbeitsblaetter.schnell', $blatt))->assertOk();
        $this->assertSame(2, AbFassung::where('blatt_id', $blatt->id)->count());

        // Benachrichtigungen sind Opt-in: standardmäßig keine
        Notification::assertNothingSent();
    }

    public function test_schnellmodus_braucht_ziel_und_ki(): void
    {
        $this->actingAs($this->lehrkraft());
        $datei = fn () => [UploadedFile::fake()->createWithContent('a.jpg', $this->bildInhalt())];

        $this->postJson(route('arbeitsblaetter.schnell.store'), ['dateien' => $datei()])
            ->assertStatus(422)->assertJsonValidationErrors('kinder');
        $this->assertSame(0, AngepasstesBlatt::count());

        $this->arbeitsblaetterEinrichten(false);
        $kind = $this->kindMitProfil();
        $this->postJson(route('arbeitsblaetter.schnell.store'), ['dateien' => $datei(), 'kinder' => [$kind->id]])
            ->assertStatus(422)->assertJsonValidationErrors('dateien');
    }

    public function test_schnellmodus_fertig_wartet_auf_erkennung_und_meldet_leeres_blatt(): void
    {
        $user = $this->lehrkraft();
        $this->actingAs($user);
        $vorlage = AbProfilVorlage::firstOrFail();
        $blatt = $this->blatt(['status' => AngepasstesBlatt::ERKENNUNG, 'erstellt_von' => $user->id, 'schnell_ziele' => ['kinder' => [], 'vorlagen' => [$vorlage->id]]]);

        $this->postJson(route('arbeitsblaetter.schnell.fertig', $blatt), ['version' => $blatt->version])->assertStatus(409);

        $blatt->update(['status' => AngepasstesBlatt::ZU_PRUEFEN, 'struktur' => ['seiten' => 1, 'bausteine' => []]]);
        $this->postJson(route('arbeitsblaetter.schnell.fertig', $blatt), ['version' => $blatt->fresh()->version])->assertStatus(422);

        // Blatt ohne Schnellmodus → Statusseite leitet in den Editor
        $normal = $this->blatt(['erstellt_von' => $user->id]);
        $this->get(route('arbeitsblaetter.schnell', $normal))->assertRedirect(route('arbeitsblaetter.edit', $normal));
    }

    public function test_ohne_worker_startet_die_seite_die_erkennung_per_anfrage(): void
    {
        config(['arbeitsblaetter.ki.erkennung_per_anfrage' => true]);
        $kind = $this->kindMitProfil();
        $this->actingAs($this->lehrkraft());

        $this->postJson(route('arbeitsblaetter.schnell.store'), [
            'dateien' => [UploadedFile::fake()->createWithContent('zoo.jpg', $this->bildInhalt())],
            'kinder' => [$kind->id],
        ])->assertOk();
        $blatt = AngepasstesBlatt::firstOrFail();

        $this->assertSame(AngepasstesBlatt::ERKENNUNG, $blatt->status, 'Upload antwortet, ohne zu erkennen');
        $this->assertCount(0, $this->erkenner->aufrufe);
        $this->getJson(route('arbeitsblaetter.daten', $blatt))->assertJsonPath('blatt.erkennung_wartet', true);

        $this->postJson(route('arbeitsblaetter.erkennen.ausfuehren', $blatt))->assertOk()
            ->assertJsonPath('blatt.status', AngepasstesBlatt::ZU_PRUEFEN)
            ->assertJsonPath('blatt.erkennung_wartet', false);
        $this->postJson(route('arbeitsblaetter.erkennen.ausfuehren', $blatt))->assertOk();
        $this->assertCount(1, $this->erkenner->aufrufe, 'kein zweiter Lauf');

        // Scheduler übernimmt Aufträge, die keine Seite angestoßen hat
        $zweites = $this->blatt(['status' => AngepasstesBlatt::ENTWURF, 'struktur' => ['seiten' => 1, 'bausteine' => []], 'ki_seiten' => 1]);
        $zweites->addMediaFromString($this->bildInhalt())->usingFileName('s.jpg')->withCustomProperties(['mime' => 'image/jpeg'])->toMediaCollection(AngepasstesBlatt::ORIGINALE);
        $service = app(\App\Services\Arbeitsblaetter\AngepasstesBlattService::class);
        $service->erkennungStarten($zweites, null);
        $this->assertSame(0, $service->ausstehendeErkennungenAusfuehren(), 'frische Aufträge bleiben der Seite');
        AngepasstesBlatt::whereKey($zweites->id)->update(['updated_at' => now()->subMinute()]);
        $this->assertSame(1, $service->ausstehendeErkennungenAusfuehren());
        $this->assertSame(AngepasstesBlatt::ZU_PRUEFEN, $zweites->fresh()->status);
    }

    public function test_benachrichtigungen_nur_wenn_eingeschaltet(): void
    {
        Notification::fake();
        $this->erkenner->fehler = new AbErkennungFehler('Kaputt.');
        $aus = $this->lehrkraft();
        $an = $this->abBenachrichtigungenAn($this->lehrkraft());

        foreach ([$aus, $an] as $user) {
            $this->actingAs($user)->post(route('arbeitsblaetter.store'), ['art' => 'upload', 'dateien' => [UploadedFile::fake()->createWithContent('a.jpg', $this->bildInhalt())]]);
        }

        Notification::assertNotSentTo($aus, AngepasstesBlattFehler::class);
        Notification::assertSentTo($an, AngepasstesBlattFehler::class);
        Notification::assertNotSentTo($aus, AngepasstesBlattZurPruefung::class);
        $this->assertFalse(config('benachrichtigungen.kategorien.arbeitsblaetter.push'));
        $this->assertSame('aus', config('benachrichtigungen.kategorien.arbeitsblaetter.mail'));
    }
}
