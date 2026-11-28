<?php

namespace Tests\Feature\AngepassteArbeitsblaetter;

use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Traits\ArbeitsblaetterTestHilfen;

/** Prüf-Editor: Speichern mit Version (409), Policy, Abbildungen nur über geschützte Route, Freigabe, Bibliothek. */
class EditorTest extends TestCase
{
    use ArbeitsblaetterTestHilfen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->arbeitsblaetterEinrichten();
    }

    public function test_speichern_und_versionskonflikt(): void
    {
        $user = $this->lehrkraft();
        $blatt = $this->blatt(['erstellt_von' => $user->id, 'status' => AngepasstesBlatt::ZU_PRUEFEN]);
        $this->actingAs($user);

        $this->putJson(route('arbeitsblaetter.update', $blatt), [
            'version' => 1,
            'titel' => 'Neuer Titel',
            'struktur' => ['bausteine' => [['typ' => 'text', 'text' => 'Hallo ___ Welt']]],
        ])->assertOk()->assertJsonPath('blatt.version', 2)->assertJsonPath('blatt.struktur.bausteine.0.inhalt.1.typ', 'luecke');

        $this->putJson(route('arbeitsblaetter.update', $blatt), ['version' => 1, 'titel' => 'Veraltet'])
            ->assertStatus(409)->assertJsonPath('blatt.titel', 'Neuer Titel');

        $this->putJson(route('arbeitsblaetter.update', $blatt), ['version' => 2, 'struktur' => ['bausteine' => [['typ' => 'video']]]])->assertStatus(422);
    }

    public function test_fremde_blaetter_nur_mit_sonderrecht(): void
    {
        $blatt = $this->blatt(['erstellt_von' => $this->lehrkraft()->id]);

        $this->actingAs($this->lehrkraft());
        $this->get(route('arbeitsblaetter.edit', $blatt))->assertOk();
        $this->putJson(route('arbeitsblaetter.update', $blatt), ['version' => 1, 'titel' => 'x'])->assertForbidden();
        $this->postJson(route('arbeitsblaetter.freigeben', $blatt), ['version' => 1])->assertForbidden();
        $this->delete(route('arbeitsblaetter.destroy', $blatt))->assertForbidden();

        $this->actingAs($this->lehrkraft(['create adapted worksheets', 'manage adapted worksheets']));
        $this->putJson(route('arbeitsblaetter.update', $blatt), ['version' => 1, 'titel' => 'x'])->assertOk();
        $this->delete(route('arbeitsblaetter.destroy', $blatt))->assertRedirect(route('arbeitsblaetter.index'));
        $this->assertSoftDeleted($blatt);

        $this->actingAs($this->lehrkraft(['view wiki']));
        $this->get(route('arbeitsblaetter.index'))->assertForbidden();
    }

    public function test_abbildungen_nur_ueber_geschuetzte_route(): void
    {
        $user = $this->lehrkraft();
        $blatt = $this->blatt(['erstellt_von' => $user->id]);
        $this->actingAs($user);

        $antwort = $this->post(route('arbeitsblaetter.abbildungen.store', $blatt), [
            'bild' => UploadedFile::fake()->createWithContent('a.png', $this->bildInhalt(100, 80, 'png')),
        ])->assertOk();
        $id = $antwort->json('id');
        $media = $blatt->getMedia(AngepasstesBlatt::ABBILDUNGEN)->first();
        $this->assertSame('arbeitsblaetter', $media->disk);

        $this->get(route('arbeitsblaetter.abbildungen.show', [$blatt, $id]))->assertOk()->assertHeader('Content-Type', 'image/png');
        $anderes = $this->blatt();
        $this->get(route('arbeitsblaetter.abbildungen.show', [$anderes, $id]))->assertNotFound();

        $this->actingAs($this->lehrkraft(['view wiki']));
        $this->get(route('arbeitsblaetter.abbildungen.show', [$blatt, $id]))->assertForbidden();

        $this->post('/logout');
        auth()->logout();
        $this->get(route('arbeitsblaetter.abbildungen.show', [$blatt, $id]))->assertRedirect();
    }

    public function test_freigabe_vorschau_duplizieren_bibliothek(): void
    {
        $user = $this->lehrkraft();
        $blatt = $this->blatt(['erstellt_von' => $user->id, 'status' => AngepasstesBlatt::ZU_PRUEFEN, 'titel' => 'Brüche Klasse 4', 'klassenstufe' => 4]);
        $this->actingAs($user);

        $this->post(route('arbeitsblaetter.vorschau', $blatt), ['profil' => 'v'.\App\Models\Arbeitsblaetter\AbProfilVorlage::where('name', 'A3 groß')->value('id')])
            ->assertOk()->assertSee('font-size: 32pt', false)->assertSee('297mm', false);

        $this->postJson(route('arbeitsblaetter.freigeben', $blatt), ['version' => 1])->assertOk()->assertJsonPath('blatt.status', 'freigegeben');
        $this->assertSame($user->id, $blatt->fresh()->geprueft_von);

        $leer = $this->blatt(['erstellt_von' => $user->id, 'status' => 'entwurf'], []);
        $this->postJson(route('arbeitsblaetter.freigeben', $leer), ['version' => 1])->assertStatus(422);

        $this->post(route('arbeitsblaetter.duplizieren', $blatt))->assertRedirect();
        $this->assertSame(1, AngepasstesBlatt::where('titel', 'Brüche Klasse 4 (Kopie)')->count());

        $this->get(route('arbeitsblaetter.index', ['q' => 'Klasse 4']))->assertOk()->assertSee('Brüche Klasse 4');
        $this->get(route('arbeitsblaetter.index', ['klassenstufe' => 5]))->assertOk()->assertDontSee('Brüche Klasse 4');
        $this->get(route('arbeitsblaetter.index', ['ansicht' => 'zur_pruefung']))->assertOk()->assertSee('(Kopie)');
        $this->get(route('arbeitsblaetter.schrift', ['atkinson', 'fett']))->assertOk();
    }
}
