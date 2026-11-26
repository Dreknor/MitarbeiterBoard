<?php

namespace Tests\Feature\Wochenplan;

use App\Models\Klasse;
use App\Models\Wochenplan\WpDatei;
use App\Services\Wochenplan\Nextcloud\WpNextcloudWebdav;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;
use Tests\Traits\WochenplanTestHilfen;

/**
 * WebDAV-Client gegen einen simulierten Nextcloud-Server (HTTP-Ebene) und der
 * Umzug bisheriger Media-Library-Arbeitsblätter.
 */
class NextcloudWebdavTest extends TestCase
{
    use WochenplanTestHilfen;

    /** @var array<string, string> simulierter Server: Pfad → Inhalt ('' + Ordner-Kennung) */
    private array $server = [];

    private array $ordner = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'nextcloud.wochenplan.url' => 'https://cloud.schule.test',
            'nextcloud.wochenplan.username' => 'wochenplan',
            'nextcloud.wochenplan.password' => 'geheim',
        ]);
        $this->einstellung('wochenplan_nextcloud_aktiv', '1');
        $this->einstellung('wochenplan_nextcloud_basisordner', '/Wochenpläne');

        $praefix = 'https://cloud.schule.test/remote.php/dav/files/wochenplan/';
        Http::fake(function (Request $request) use ($praefix) {
            $this->assertTrue($request->hasHeader('Authorization'), 'Basic-Auth fehlt');
            $pfad = rawurldecode(rtrim(substr($request->url(), strlen($praefix)), '/'));

            switch ($request->method()) {
                case 'MKCOL':
                    if (isset($this->ordner[$pfad])) {
                        return Http::response('', 405);
                    }
                    $this->ordner[$pfad] = true;

                    return Http::response('', 201);
                case 'PUT':
                    $eltern = dirname($pfad);
                    if (! isset($this->ordner[$eltern])) {
                        return Http::response('', 409);
                    }
                    $this->server[$pfad] = $request->body();

                    return Http::response('', 201);
                case 'GET':
                    return isset($this->server[$pfad]) ? Http::response($this->server[$pfad], 200) : Http::response('', 404);
                case 'DELETE':
                    unset($this->server[$pfad]);

                    return Http::response('', 204);
                case 'MOVE':
                    $ziel = rawurldecode(rtrim(substr($request->header('Destination')[0], strlen($praefix)), '/'));
                    $gefunden = false;
                    foreach ([$this->server, $this->ordner] as $i => $liste) {
                        foreach ($liste as $p => $v) {
                            if ($p === $pfad || str_starts_with($p, $pfad.'/')) {
                                $neu = $ziel.substr($p, strlen($pfad));
                                if ($i === 0) { unset($this->server[$p]); $this->server[$neu] = $v; }
                                else { unset($this->ordner[$p]); $this->ordner[$neu] = true; }
                                $gefunden = true;
                            }
                        }
                    }

                    return Http::response('', $gefunden ? 201 : 404);
                case 'PROPFIND':
                    $existiert = isset($this->server[$pfad]) || isset($this->ordner[$pfad]);
                    if (! $existiert) {
                        return Http::response('', 404);
                    }
                    $antworten = '<d:response><d:href>/remote.php/dav/files/wochenplan/'.implode('/', array_map('rawurlencode', explode('/', $pfad))).'/</d:href><d:propstat><d:prop><d:resourcetype><d:collection/></d:resourcetype></d:prop></d:propstat></d:response>';
                    foreach ($this->server as $p => $inhalt) {
                        if (dirname($p) === $pfad) {
                            $href = '/remote.php/dav/files/wochenplan/'.implode('/', array_map('rawurlencode', explode('/', $p)));
                            $antworten .= '<d:response><d:href>'.$href.'</d:href><d:propstat><d:prop><d:resourcetype/><d:getcontentlength>'.strlen($inhalt).'</d:getcontentlength><d:getcontenttype>application/pdf</d:getcontenttype></d:prop></d:propstat></d:response>';
                        }
                    }

                    return Http::response('<?xml version="1.0"?><d:multistatus xmlns:d="DAV:">'.$antworten.'</d:multistatus>', 207);
            }

            return Http::response('', 400);
        });
    }

    public function test_webdav_grundfunktionen(): void
    {
        $nc = app(WpNextcloudWebdav::class);
        $this->assertTrue($nc->aktiv());

        $this->assertTrue($nc->hochladen('%PDF-1', '2026-27/3a/KW48 Herbst/Plan.pdf'));
        $this->assertArrayHasKey('Wochenpläne/2026-27/3a/KW48 Herbst/Plan.pdf', $this->server, 'Basisordner und Umlaute/Leerzeichen korrekt kodiert');
        $this->assertSame('%PDF-1', $nc->herunterladen('2026-27/3a/KW48 Herbst/Plan.pdf'));
        $this->assertTrue($nc->existiert('2026-27/3a/KW48 Herbst/Plan.pdf'));

        $liste = $nc->liste('2026-27/3a/KW48 Herbst');
        $this->assertSame('Plan.pdf', $liste[0]['name']);
        $this->assertSame('2026-27/3a/KW48 Herbst/Plan.pdf', $liste[0]['pfad']);
        $this->assertFalse($liste[0]['ordner']);

        $this->assertTrue($nc->verschieben('2026-27/3a/KW48 Herbst', '2026-27/3a/KW48 Winter'));
        $this->assertSame('%PDF-1', $nc->herunterladen('2026-27/3a/KW48 Winter/Plan.pdf'));
        $this->assertFalse($nc->existiert('2026-27/3a/KW48 Herbst/Plan.pdf'));

        $this->assertTrue($nc->loeschen('2026-27/3a/KW48 Winter/Plan.pdf'));
        $this->assertFalse($nc->herunterladen('2026-27/3a/KW48 Winter/Plan.pdf'));
        $this->assertStringContainsString('dir=%2FWochenpl%C3%A4ne%2F2026-27', (string) $nc->webLink('2026-27/3a', true));
    }

    public function test_server_nicht_erreichbar_liefert_false(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response('', 503)]);
        $nc = app(WpNextcloudWebdav::class);

        $this->assertFalse($nc->hochladen('x', 'a/b.pdf'));
        $this->assertFalse($nc->herunterladen('a/b.pdf'));
        $this->assertFalse($nc->existiert('a'));
        $this->assertSame([], $nc->liste('a'));
    }

    public function test_ablage_eines_plans_ueber_webdav(): void
    {
        Storage::fake('wochenplan');
        $klasse = Klasse::factory()->create(['name' => '3a']);
        $plan = $this->klassenplan($klasse, ['name' => 'Herbst', 'gueltig_von' => '2026-11-23', 'gueltig_bis' => '2026-11-27']);
        $this->fachMitAufgaben($plan, ['Lesen']);
        $ablage = app(\App\Services\Wochenplan\WpAblageService::class);

        $this->assertTrue($ablage->ablegen($plan), (string) $plan->fresh()->ablage_fehler);
        $datei = $ablage->dateiAusInhalt($plan->fresh(), 'Blatt.pdf', '%PDF-blatt', 'application/pdf', null);

        $this->assertArrayHasKey('Wochenpläne/2026-27/3a/2026-KW48 Herbst/Herbst.pdf', $this->server);
        $this->assertArrayHasKey('Wochenpläne/2026-27/3a/2026-KW48 Herbst/Herbst.docx', $this->server);
        $this->assertSame(WpDatei::ABGELEGT, $datei->fresh()->status);
        $this->assertSame('%PDF-blatt', $this->server['Wochenpläne/2026-27/3a/2026-KW48 Herbst/Arbeitsblätter/Blatt.pdf']);

        $plan->update(['name' => 'Winter']);
        $this->assertTrue($ablage->ablegen($plan->fresh()));
        $this->assertArrayHasKey('Wochenpläne/2026-27/3a/2026-KW48 Winter/Arbeitsblätter/Blatt.pdf', $this->server);
        $this->assertArrayNotHasKey('Wochenpläne/2026-27/3a/2026-KW48 Herbst/Herbst.pdf', $this->server);
        $this->assertSame('%PDF-blatt', $ablage->dateiInhalt($datei->fresh()));
    }

    public function test_umzug_der_bisherigen_arbeitsblaetter(): void
    {
        Storage::fake('wochenplan');
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
        $klasse = Klasse::factory()->create(['name' => '3a']);
        $plan = $this->klassenplan($klasse, ['name' => 'Herbst', 'gueltig_von' => '2026-11-23', 'gueltig_bis' => '2026-11-27']);
        $kind = \App\Models\Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $kp = app(\App\Services\Wochenplan\WochenplanService::class)->kinderplanErstellen($plan, $kind);

        $eigene = $this->altesMedium($plan->id, 'blatt.pdf', '%PDF-alt', []);
        $kopie = $this->altesMedium($kp->id, 'blatt.pdf', '%PDF-alt', ['synced_from_plan_id' => $plan->id]);

        $this->artisan('wochenplan:nextcloud-umzug', ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('wp_dateien', 0);

        $this->artisan('wochenplan:nextcloud-umzug')->assertSuccessful();

        $this->assertDatabaseMissing('media', ['id' => $eigene->id]);
        $this->assertDatabaseMissing('media', ['id' => $kopie->id]);
        $datei = WpDatei::where('wp_plan_id', $plan->id)->firstOrFail();
        $this->assertSame(WpDatei::ABGELEGT, $datei->status);
        $this->assertSame('%PDF-alt', app(\App\Services\Wochenplan\WpAblageService::class)->dateiInhalt($datei));
        $this->assertSame(0, WpDatei::where('wp_plan_id', $kp->id)->count(), 'Kinderplan verweist auf den Klassenplan');
        $this->assertCount(1, $kp->fresh()->alleDateien());
        // Plan wurde im Zuge des Umzugs neu abgelegt (sync-Queue)
        $this->assertArrayHasKey('Wochenpläne/2026-27/3a/2026-KW48 Herbst/Herbst.pdf', $this->server);
    }

    private function altesMedium(int $planId, string $name, string $inhalt, array $eigenschaften): Media
    {
        $media = Media::create([
            'model_type' => \App\Models\Wochenplan\WpPlan::class,
            'model_id' => $planId,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'collection_name' => 'arbeitsblaetter',
            'name' => pathinfo($name, PATHINFO_FILENAME),
            'file_name' => $name,
            'mime_type' => 'application/pdf',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => strlen($inhalt),
            'manipulations' => [],
            'custom_properties' => $eigenschaften,
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
        Storage::disk('public')->put($media->id.'/'.$name, $inhalt);

        return $media;
    }
}
