<?php

namespace Tests\Feature\Wiki;

use App\Models\WikiSeite;
use App\Services\Wiki\WikiAnhangService;
use App\Services\Wiki\WikiService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class WikiAnhaengeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('wiki');
    }

    public function test_bild_hochladen_verkleinert_und_liefert_location(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $seite = app(WikiService::class)->anlegen($user, ['titel' => 'Bilder']);

        $antwort = $this->post(route('wiki.anhaenge.store', $seite), ['datei' => UploadedFile::fake()->image('Screenshot 1.png', 2400, 1200)], ['Accept' => 'application/json'])
            ->assertCreated();

        $media = Media::findOrFail($antwort->json('dateien.0.id'));
        $this->assertSame(WikiSeite::BILDER, $media->collection_name);
        $this->assertSame('wiki', $media->disk);
        $this->assertSame(route('wiki.anhang', $media, false), $antwort->json('location'));
        [$breite] = getimagesize($media->getPath());
        $this->assertSame(WikiAnhangService::MAX_KANTE, $breite);

        $this->get(route('wiki.anhang', $media))->assertOk();
    }

    public function test_dateien_nur_mit_bearbeitungsrecht_und_auslieferung_nur_mit_leserecht(): void
    {
        $autor = $this->createUserWithPermission('view wiki', 'edit wiki');
        $seite = app(WikiService::class)->anlegen($autor, ['titel' => 'Dateien']);

        $this->actingAsWithPermission('view wiki');
        $this->post(route('wiki.anhaenge.store', $seite), ['datei' => UploadedFile::fake()->create('plan.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->actingAs($autor);
        $id = $this->post(route('wiki.anhaenge.store', $seite), ['dateien' => [UploadedFile::fake()->create('plan.pdf', 20, 'application/pdf')]], ['Accept' => 'application/json'])
            ->assertCreated()->json('dateien.0.id');
        $media = Media::findOrFail($id);
        $this->assertSame(WikiSeite::DATEIEN, $media->collection_name);
        $this->get(route('wiki.show', $seite->slug))->assertSee('plan.pdf');

        // ohne Leserecht nicht abrufbar, auch nicht über die alte /image-Route
        $this->actingAsWithPermission('use dashboard v2');
        $this->get(route('wiki.anhang', $media))->assertForbidden();
        $this->get(route('image.get', $media))->assertNotFound();
    }

    public function test_base64_bilder_werden_beim_speichern_zu_anhaengen(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $png = base64_encode(UploadedFile::fake()->image('x.png', 20, 20)->getContent());

        $seite = app(WikiService::class)->anlegen($user, [
            'titel' => 'Eingebettet',
            'inhalt' => '<p><img src="data:image/png;base64,'.$png.'"></p><p><img src="data:image/png;base64,'.$png.'"></p>',
        ]);

        $this->assertStringNotContainsString('base64', $seite->inhalt);
        $this->assertStringContainsString('/wiki/anhang/', $seite->inhalt);
        $this->assertSame(1, $seite->getMedia(WikiSeite::BILDER)->count());
    }

    public function test_entfernen_und_bereinigen_nach_90_tagen(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $wiki = app(WikiService::class);
        $seite = $wiki->anlegen($user, ['titel' => 'Aufräumen']);
        $anhaenge = app(WikiAnhangService::class);

        $datei = $anhaenge->hochladen($seite, UploadedFile::fake()->create('alt.pdf', 10, 'application/pdf'), $user);
        $verlinkt = $anhaenge->hochladen($seite, UploadedFile::fake()->image('verlinkt.png', 30, 30), $user);
        $verwaist = $anhaenge->hochladen($seite, UploadedFile::fake()->image('verwaist.png', 30, 30), $user);
        $wiki->speichern($seite, $user, ['inhalt' => '<p><img src="'.$anhaenge->url($verlinkt).'"></p>'], $seite->version);

        $this->deleteJson(route('wiki.anhaenge.destroy', [$seite, $datei]))->assertOk();
        $this->assertNotNull($datei->fresh()->getCustomProperty('entfernt_at'));
        $this->get(route('wiki.show', $seite->slug))->assertDontSee('alt.pdf');

        $this->assertSame(0, $anhaenge->bereinigen());

        $this->travel(91)->days();
        $this->assertSame(2, $anhaenge->bereinigen());
        $this->assertNull(Media::find($datei->id));
        $this->assertNull(Media::find($verwaist->id));
        $this->assertNotNull(Media::find($verlinkt->id));
    }
}
