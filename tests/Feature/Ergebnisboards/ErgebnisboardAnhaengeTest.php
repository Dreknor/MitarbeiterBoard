<?php

namespace Tests\Feature\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Anhänge an Karten: private Disk `ergebnisboards`, Auslieferung nur über ergebnisboards.karten.dateien.show.
 */
class ErgebnisboardAnhaengeTest extends TestCase
{
    use ErgebnisboardTestHelfer;

    private User $autor;

    private Ergebnisboard $board;

    private ErgebnisKarte $karte;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('ergebnisboards');
        $this->withoutVite();

        $this->autor = $this->nutzer(['view result boards', 'create result boards']);
        $this->board = $this->board($this->autor, 'kanban');
        $this->karte = ErgebnisKarte::factory()->create(['board_id' => $this->board->id, 'bereich' => 'ideen']);
    }

    private function hochladen(User $user, array $dateien, ?ErgebnisKarte $karte = null)
    {
        return $this->actingAs($user)->post(
            route('ergebnisboards.karten.dateien.store', [$this->board, $karte ?? $this->karte]),
            ['dateien' => $dateien],
            ['Accept' => 'application/json']
        );
    }

    private function anhang(?ErgebnisKarte $karte = null, string $name = 'protokoll.pdf'): Media
    {
        return ($karte ?? $this->karte)
            ->addMedia(UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%Test\n"))
            ->toMediaCollection(ErgebnisKarte::ANHAENGE);
    }

    public function test_upload_mit_schreibrecht_landet_auf_privater_disk(): void
    {
        $this->hochladen($this->autor, [
            UploadedFile::fake()->create('Ergebnis Klausur.pdf', 50, 'application/pdf'),
            UploadedFile::fake()->image('foto.png'),
        ])->assertCreated()
            ->assertJsonPath('data.version', 3)
            ->assertJsonCount(2, 'data.karten.0.anhaenge')
            ->assertJsonPath('data.karten.0.anhaenge.0.name', 'Ergebnis Klausur.pdf');

        $medien = $this->karte->fresh()->getMedia(ErgebnisKarte::ANHAENGE);
        $this->assertCount(2, $medien);
        foreach ($medien as $media) {
            $this->assertSame('ergebnisboards', $media->disk);
            Storage::disk('ergebnisboards')->assertExists($media->getPathRelativeToRoot());
        }
    }

    public function test_upload_ohne_schreibrecht_verboten(): void
    {
        $leser = $this->nutzer();
        $this->zugriff($this->board, $leser, 'lesen');

        $this->hochladen($leser, [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertForbidden();
        $this->hochladen($this->nutzer(), [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertForbidden();

        $this->assertSame(0, Media::count());
    }

    public function test_upload_auf_archiviertem_board_verboten(): void
    {
        $this->board->update(['archiviert_at' => now()]);

        $this->hochladen($this->autor, [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertForbidden();
    }

    public function test_unzulaessige_dateitypen_werden_abgelehnt(): void
    {
        $this->hochladen($this->autor, [UploadedFile::fake()->createWithContent('virus.exe', "MZ\x90\x00binary")])
            ->assertStatus(422)->assertJsonValidationErrors('dateien.0');
        $this->hochladen($this->autor, [UploadedFile::fake()->createWithContent('seite.html', '<html><script>alert(1)</script></html>')])
            ->assertStatus(422)->assertJsonValidationErrors('dateien.0');
        $this->hochladen($this->autor, [UploadedFile::fake()->createWithContent('bild.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
            ->assertStatus(422)->assertJsonValidationErrors('dateien.0');
        $this->hochladen($this->autor, [])->assertStatus(422)->assertJsonValidationErrors('dateien');

        $this->assertSame(0, Media::count());
    }

    public function test_zu_grosse_datei_wird_abgelehnt(): void
    {
        $this->hochladen($this->autor, [UploadedFile::fake()->create('riesig.pdf', 20481, 'application/pdf')])
            ->assertStatus(422)->assertJsonValidationErrors('dateien.0');
    }

    public function test_download_nur_mit_leserecht(): void
    {
        $media = $this->anhang();
        $leser = $this->nutzer();
        $this->zugriff($this->board, $leser, 'lesen');
        $url = route('ergebnisboards.karten.dateien.show', [$this->board, $this->karte, $media]);

        $antwort = $this->actingAs($leser)->get($url)->assertOk();
        $this->assertSame('application/pdf', $antwort->headers->get('Content-Type'));
        $this->assertSame('nosniff', $antwort->headers->get('X-Content-Type-Options'));
        $this->assertStringStartsWith('inline', $antwort->headers->get('Content-Disposition'));

        $this->actingAs($this->nutzer())->get($url)->assertForbidden();
    }

    public function test_nicht_anzeigbare_typen_werden_als_download_ausgeliefert(): void
    {
        $media = $this->karte
            ->addMedia(UploadedFile::fake()->createWithContent('archiv.zip', "PK\x05\x06".str_repeat("\x00", 18)))
            ->toMediaCollection(ErgebnisKarte::ANHAENGE);

        $antwort = $this->actingAs($this->autor)->get(route('ergebnisboards.karten.dateien.show', [$this->board, $this->karte, $media]))
            ->assertOk();
        $this->assertSame('application/octet-stream', $antwort->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment', $antwort->headers->get('Content-Disposition'));
    }

    public function test_anhang_einer_anderen_karte_ergibt_404(): void
    {
        $andereKarte = ErgebnisKarte::factory()->create(['board_id' => $this->board->id, 'bereich' => 'ideen']);
        $fremd = $this->anhang($andereKarte);

        $this->actingAs($this->autor)->get(route('ergebnisboards.karten.dateien.show', [$this->board, $this->karte, $fremd]))
            ->assertNotFound();
        $this->actingAs($this->autor)->deleteJson(route('ergebnisboards.karten.dateien.destroy', [$this->board, $this->karte, $fremd]))
            ->assertNotFound();
        $this->assertModelExists($fremd);

        // Media eines ganz anderen Modells (gleiche ID-Nummernkreise)
        $anderesBoard = $this->board($this->autor);
        $karteAnderesBoard = ErgebnisKarte::factory()->create(['board_id' => $anderesBoard->id, 'bereich' => 'ideen']);
        $media = $this->anhang($karteAnderesBoard);
        $this->actingAs($this->autor)->get(route('ergebnisboards.karten.dateien.show', [$this->board, $karteAnderesBoard, $media]))
            ->assertNotFound();
    }

    public function test_anhang_loeschen_nur_mit_schreibrecht(): void
    {
        $media = $this->anhang();
        $pfad = $media->getPathRelativeToRoot();
        $leser = $this->nutzer();
        $this->zugriff($this->board, $leser, 'lesen');
        $url = route('ergebnisboards.karten.dateien.destroy', [$this->board, $this->karte, $media]);

        $this->actingAs($leser)->deleteJson($url)->assertForbidden();
        $this->assertModelExists($media);

        $this->actingAs($this->autor)->deleteJson($url)->assertOk()->assertJsonCount(0, 'data.karten.0.anhaenge');
        $this->assertModelMissing($media);
        Storage::disk('ergebnisboards')->assertMissing($pfad);
    }

    public function test_karte_loeschen_entfernt_anhaenge(): void
    {
        $media = $this->anhang();
        $pfad = $media->getPathRelativeToRoot();
        Storage::disk('ergebnisboards')->assertExists($pfad);

        $this->actingAs($this->autor)->deleteJson(route('ergebnisboards.karten.destroy', [$this->board, $this->karte]))->assertOk();

        $this->assertSame(0, Media::count());
        Storage::disk('ergebnisboards')->assertMissing($pfad);
    }

    public function test_board_loeschen_entfernt_anhaenge(): void
    {
        $media = $this->anhang();
        $pfad = $media->getPathRelativeToRoot();

        $this->actingAs($this->autor)->delete(route('ergebnisboards.destroy', $this->board))->assertRedirect();

        $this->assertSame(0, Media::count());
        Storage::disk('ergebnisboards')->assertMissing($pfad);
    }
}
