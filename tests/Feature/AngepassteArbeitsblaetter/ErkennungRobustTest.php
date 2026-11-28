<?php

namespace Tests\Feature\AngepassteArbeitsblaetter;

use App\Jobs\Arbeitsblaetter\AbBlattErkennen;
use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Notifications\AngepasstesBlattFehler;
use App\Services\Arbeitsblaetter\AngepasstesBlattService;
use App\Services\Arbeitsblaetter\Ki\AbErkenner;
use App\Services\Arbeitsblaetter\Ki\AbErkennerFabrik;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;
use Tests\Traits\ArbeitsblaetterTestHilfen;

/**
 * Überlastete KI (z. B. Gemini 503): neue Versuche auch ohne Queue-Worker, Ersatzmodell,
 * hängende Erkennungen werden beendet, Erkennung lässt sich abbrechen.
 */
class ErkennungRobustTest extends TestCase
{
    use ArbeitsblaetterTestHilfen;

    private const ANTWORT = ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{"seiten":1,"bausteine":[{"typ":"ueberschrift","text":"Zoo"}]}']]]]],
        'usageMetadata' => ['promptTokenCount' => 100, 'candidatesTokenCount' => 10]];

    protected function setUp(): void
    {
        parent::setUp();
        $this->arbeitsblaetterEinrichten();
        config([
            'arbeitsblaetter.ki.treiber' => 'gemini',
            'arbeitsblaetter.ki.gemini.schluessel' => 'g',
            'arbeitsblaetter.ki.gemini.modell' => 'gemini-flash-latest',
            'arbeitsblaetter.ki.wiederholung_sekunden' => [0, 0],
        ]);
        $this->app->forgetInstance(AbErkenner::class);
        $this->app->bind(AbErkenner::class, fn () => AbErkennerFabrik::erstellen());
    }

    private function hochladen(): AngepasstesBlatt
    {
        $this->actingAs($this->abBenachrichtigungenAn($this->lehrkraft()));
        $this->post(route('arbeitsblaetter.store'), ['art' => 'upload', 'dateien' => [UploadedFile::fake()->createWithContent('zoo.jpg', $this->bildInhalt())]])->assertRedirect();

        return AngepasstesBlatt::firstOrFail();
    }

    public function test_ohne_worker_wird_bei_503_erneut_versucht(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'The model is overloaded.']], 503)
            ->push(['error' => ['message' => 'The model is overloaded.']], 503)
            ->push(self::ANTWORT)]);

        $blatt = $this->hochladen();

        $this->assertSame(AngepasstesBlatt::ZU_PRUEFEN, $blatt->status, 'dritter Versuch klappt');
        $this->assertNull($blatt->fehler);
        $this->assertSame('Zoo', $blatt->strukturGueltig()['bausteine'][0]['text']);
        Http::assertSentCount(3);
    }

    public function test_ohne_worker_dauerhaft_503_endet_mit_fehler_statt_haengen(): void
    {
        Notification::fake();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'The model is overloaded.']], 503)]);

        $blatt = $this->hochladen();

        $this->assertSame(AngepasstesBlatt::FEHLER, $blatt->status);
        $this->assertStringContainsString('überlastet (HTTP 503: The model is overloaded.)', $blatt->fehler);
        $this->assertStringContainsString('nach 3 Versuchen', $blatt->fehler);
        Notification::assertSentTo($blatt->ersteller, AngepasstesBlattFehler::class);
        Http::assertSentCount(6); // je Versuch Hauptmodell + eine kurze Wiederholung
    }

    public function test_ersatzmodell_bei_ueberlastung(): void
    {
        config(['arbeitsblaetter.ki.gemini.ersatzmodell' => 'gemini-flash-lite-latest']);
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent' => Http::response(['error' => ['message' => 'overloaded']], 503),
            'generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent' => Http::response(self::ANTWORT),
        ]);

        $blatt = $this->hochladen();

        $this->assertSame(AngepasstesBlatt::ZU_PRUEFEN, $blatt->status);
        Http::assertSentCount(3); // Hauptmodell, kurze Wiederholung, Ersatzmodell
        $this->assertStringContainsString('Ersatzmodell „gemini-flash-lite-latest“', $blatt->ki_hinweise[0]);
        $this->assertSame(503, \App\Models\Arbeitsblaetter\AbKiProtokoll::oldest('id')->first()->http_status);
        $this->assertStringContainsString('HTTP 503: overloaded', \App\Models\Arbeitsblaetter\AbKiProtokoll::oldest('id')->first()->fehler, 'Fehlertext steht im KI-Protokoll');
    }

    public function test_mit_worker_wird_zurueckgelegt_und_hinweis_gezeigt(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'overloaded']], 503)]);
        $blatt = $this->blatt(['status' => AngepasstesBlatt::ERKENNUNG], []);
        $blatt->addMediaFromString($this->bildInhalt())->usingFileName('s.jpg')->withCustomProperties(['mime' => 'image/jpeg'])->toMediaCollection(AngepasstesBlatt::ORIGINALE);

        $queueJob = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->once()->with(60);
        $queueJob->shouldReceive('isReleased', 'isDeleted', 'hasFailed')->andReturn(false);
        $job = new AbBlattErkennen($blatt->id);
        $job->setJob($queueJob);
        $job->handle(app(AngepasstesBlattService::class), app(AbErkenner::class));

        $blatt->refresh();
        $this->assertSame(AngepasstesBlatt::ERKENNUNG, $blatt->status);
        $this->assertStringContainsString('Neuer Versuch in 1 Minute', $blatt->fehler);
    }

    public function test_haengende_erkennung_wird_beendet_und_kann_neu_gestartet_werden(): void
    {
        $user = $this->lehrkraft();
        $blatt = $this->blatt(['status' => AngepasstesBlatt::ERKENNUNG, 'erstellt_von' => $user->id, 'fehler' => 'Gemini ist überlastet (HTTP 503).'], []);
        AngepasstesBlatt::whereKey($blatt->id)->update(['updated_at' => now()->subMinutes(20)]);
        $this->actingAs($user);

        $this->getJson(route('arbeitsblaetter.daten', $blatt))->assertOk()
            ->assertJsonPath('blatt.status', 'fehler')
            ->assertJsonPath('blatt.fehler', 'Die Erkennung hat nicht rechtzeitig geantwortet. Letzte Meldung: Gemini ist überlastet (HTTP 503).');

        // frische Erkennungen bleiben unberührt
        $frisch = $this->blatt(['status' => AngepasstesBlatt::ERKENNUNG], []);
        $this->assertSame(0, app(AngepasstesBlattService::class)->haengendeErkennungenBeenden());
        $this->assertSame(AngepasstesBlatt::ERKENNUNG, $frisch->fresh()->status);
    }

    public function test_erkennung_abbrechen(): void
    {
        $user = $this->lehrkraft();
        $blatt = $this->blatt(['status' => AngepasstesBlatt::ERKENNUNG, 'erstellt_von' => $user->id], []);
        $this->actingAs($user);

        $this->postJson(route('arbeitsblaetter.erkennen.abbrechen', $blatt))->assertOk()->assertJsonPath('blatt.status', 'entwurf');

        // ein später laufender Auftrag ändert nichts mehr
        Http::fake();
        (new AbBlattErkennen($blatt->id))->handle(app(AngepasstesBlattService::class), app(AbErkenner::class));
        Http::assertNothingSent();
        $this->assertSame(AngepasstesBlatt::ENTWURF, $blatt->fresh()->status);

        $this->actingAs($this->lehrkraft());
        $this->postJson(route('arbeitsblaetter.erkennen.abbrechen', $blatt))->assertForbidden();
    }
}
