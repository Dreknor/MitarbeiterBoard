<?php

namespace Tests\Feature\Users;

use App\Models\personal\EmployeData;
use App\Models\User;
use App\Services\Personal\GeburtstagImportService as Import;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use OwenIt\Auditing\Models\Audit;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class GeburtstagImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-11-19 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  list<list<mixed>>  $zeilen  inkl. Kopfzeile
     */
    private function datei(array $zeilen, string $name = 'geburtstage.xlsx'): UploadedFile
    {
        $tabelle = new Spreadsheet();
        $blatt = $tabelle->getActiveSheet();
        foreach ($zeilen as $r => $zeile) {
            foreach ($zeile as $c => $wert) {
                $blatt->setCellValue([$c + 1, $r + 1], $wert);
            }
        }
        $pfad = tempnam(sys_get_temp_dir(), 'geb') . '.xlsx';
        (new Xlsx($tabelle))->save($pfad);

        return new UploadedFile($pfad, $name, null, null, true);
    }

    private function hochladen(array $zeilen): string
    {
        $response = $this->post(route('users.geburtstage.vorschau'), ['datei' => $this->datei($zeilen)]);
        $response->assertRedirect();
        preg_match('#/([A-Za-z0-9]{32})$#', $response->headers->get('Location'), $m);
        $this->assertNotEmpty($m, 'Kein Token in der Weiterleitung');

        return $m[1];
    }

    /** @test */
    public function ohne_recht_edit_users_kein_zugriff(): void
    {
        $this->actingAsWithPermission('edit employe');

        $this->get(route('users.geburtstage.index'))->assertForbidden();
        $this->post(route('users.geburtstage.vorschau'), ['datei' => $this->datei([['E-Mail', 'Geburtstag']])])->assertForbidden();
    }

    /** @test */
    public function seite_und_beispieldatei_sind_erreichbar(): void
    {
        $this->actingAsWithPermission('edit users');

        $this->get(route('users.geburtstage.index'))->assertOk()->assertSee('Geburtstage importieren');
        $this->get(route('users.geburtstage.vorlage'))->assertOk()->assertDownload('Geburtstage_Vorlage.xlsx');
        $this->get(url('users'))->assertOk()->assertSee(route('users.geburtstage.index'));
    }

    /** @test */
    public function vorschau_speichert_nichts_und_uebernehmen_setzt_nur_leere_geburtstage(): void
    {
        $this->actingAsWithPermission('edit users');

        $ohneDaten = User::factory()->create(['email' => 'ohne.daten@example.org']);
        $leer = User::factory()->create(['email' => 'leer@example.org']);
        EmployeData::create(['user_id' => $leer->id, 'vorname' => 'Lea', 'geschlecht' => 'weiblich']);
        $gleich = User::factory()->create(['email' => 'gleich@example.org']);
        EmployeData::create(['user_id' => $gleich->id, 'geburtstag' => '1980-12-24']);
        $anders = User::factory()->create(['email' => 'anders@example.org']);
        EmployeData::create(['user_id' => $anders->id, 'geburtstag' => '1975-01-01']);

        $token = $this->hochladen([
            ['Name', 'E-Mail', 'Geburtsdatum'],
            ['A', '  Ohne.Daten@EXAMPLE.org ', '03.04.1990'],
            ['B', 'leer@example.org', ExcelDate::PHPToExcel(new \DateTime('1985-06-15'))],
            ['C', 'gleich@example.org', '1980-12-24'],
            ['D', 'anders@example.org', '24.12.1980'],
            ['E', 'unbekannt@example.org', '01.01.1990'],
        ]);

        $this->assertNull($ohneDaten->fresh()->employe_data);

        $this->get(route('users.geburtstage.show', $token))
            ->assertOk()
            ->assertSee('Wird gesetzt')
            ->assertSee('Bereits gesetzt – anderes Datum')
            ->assertSee('01.01.1975')
            ->assertSee('E-Mail nicht gefunden')
            ->assertSee('2 übernehmen');

        $this->post(route('users.geburtstage.store', $token))
            ->assertRedirect(route('users.geburtstage.index'))
            ->assertSessionHas('Meldung', '2 Geburtstag(e) übernommen, 3 Zeile(n) übersprungen.');

        $neu = $ohneDaten->fresh()->employe_data;
        $this->assertSame('1990-04-03', $neu->geburtstag->toDateString());
        $this->assertNull($neu->geschlecht, 'Neuer Datensatz darf kein Geschlecht vorbelegen');
        $this->assertSame('1985-06-15', $leer->fresh()->employe_data->geburtstag->toDateString());
        $this->assertSame('weiblich', $leer->fresh()->employe_data->geschlecht);
        $this->assertSame('1975-01-01', $anders->fresh()->employe_data->geburtstag->toDateString());

        // Vorschau ist verbraucht
        $this->post(route('users.geburtstage.store', $token))->assertSessionHas('type', 'warning');
    }

    /** @test */
    public function geloeschte_konten_gelten_als_nicht_gefunden(): void
    {
        $this->actingAsWithPermission('edit users');
        $weg = User::factory()->create(['email' => 'weg@example.org']);
        $weg->delete();

        $token = $this->hochladen([['E-Mail', 'Geburtstag'], ['weg@example.org', '01.01.1990']]);
        $this->post(route('users.geburtstage.store', $token));

        $this->assertNull(EmployeData::where('user_id', $weg->id)->first());
    }

    /** @test */
    public function zwischenzeitlich_gesetzter_geburtstag_wird_nicht_ueberschrieben(): void
    {
        $this->actingAsWithPermission('edit users');
        $user = User::factory()->create(['email' => 'p@example.org']);

        $token = $this->hochladen([['E-Mail', 'Geburtstag'], ['p@example.org', '01.01.1990']]);
        EmployeData::create(['user_id' => $user->id, 'geburtstag' => '1991-02-02']);

        $this->post(route('users.geburtstage.store', $token))
            ->assertSessionHas('Meldung', '0 Geburtstag(e) übernommen, 1 Zeile(n) übersprungen.');
        $this->assertSame('1991-02-02', $user->fresh()->employe_data->geburtstag->toDateString());
    }

    /** @test */
    public function datei_ohne_passende_kopfzeile_wird_abgelehnt(): void
    {
        $this->actingAsWithPermission('edit users');

        $this->post(route('users.geburtstage.vorschau'), ['datei' => $this->datei([['Mail-Adr', 'Datum'], ['a@b.de', '01.01.1990']])])
            ->assertSessionHasErrors('datei');
        $this->post(route('users.geburtstage.vorschau'), ['datei' => UploadedFile::fake()->create('x.pdf', 10)])
            ->assertSessionHasErrors('datei');
    }

    /** @test */
    public function uebernahme_erscheint_im_aenderungsverlauf(): void
    {
        config(['audit.console' => true]);
        EmployeData::observe(new \OwenIt\Auditing\AuditableObserver);

        $bearbeiter = $this->actingAsWithPermission('edit users');
        $user = User::factory()->create(['email' => 'p@example.org']);
        EmployeData::create(['user_id' => $user->id]);

        $token = $this->hochladen([['E-Mail', 'Geburtstag'], ['p@example.org', '01.01.1990']]);
        $this->post(route('users.geburtstage.store', $token));

        $audit = Audit::where('auditable_type', EmployeData::class)->where('event', 'updated')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame($bearbeiter->id, (int) $audit->user_id);
        $this->assertArrayHasKey('geburtstag', $audit->new_values);
    }

    // =========================================================================
    // Auswertung (ohne HTTP)
    // =========================================================================

    /** @test */
    public function datumsformate_und_plausibilitaet(): void
    {
        $import = app(Import::class);
        $datum = fn ($wert) => $import->datumLesen($wert)['datum']?->toDateString();
        $fehler = fn ($wert) => $import->datumLesen($wert)['fehler'];

        $this->assertSame('1980-12-24', $datum('24.12.1980'));
        $this->assertSame('1980-02-03', $datum('3.2.1980'));
        $this->assertSame('1980-12-24', $datum('1980-12-24'));
        $this->assertSame('1980-12-24', $datum(ExcelDate::PHPToExcel(new \DateTime('1980-12-24'))));
        $this->assertSame('1980-12-24', $datum((string) ExcelDate::PHPToExcel(new \DateTime('1980-12-24'))));

        $this->assertStringContainsString('Zweistelliges Jahr', $fehler('24.12.80'));
        $this->assertStringContainsString('Kein gültiges Datum', $fehler('31.02.1980'));
        $this->assertStringContainsString('Zukunft', $fehler('01.01.2027'));
        $this->assertStringContainsString('Unplausibles Alter', $fehler('01.01.2020'));
        $this->assertStringContainsString('Unplausibles Alter', $fehler('01.01.1900'));
        $this->assertStringContainsString('Unbekanntes Format', $fehler('Dezember 1980'));
        $this->assertSame('Kein Datum angegeben', $fehler(''));
    }

    /** @test */
    public function doppelte_zeilen_gleiches_datum_einmal_unterschiedlich_konflikt(): void
    {
        User::factory()->create(['email' => 'a@example.org']);
        User::factory()->create(['email' => 'b@example.org']);

        $auswertung = app(Import::class)->auswerten([
            ['zeile' => 2, 'email' => 'a@example.org', 'wert' => '01.01.1990'],
            ['zeile' => 3, 'email' => 'A@example.org', 'wert' => '1990-01-01'],
            ['zeile' => 4, 'email' => 'b@example.org', 'wert' => '01.01.1990'],
            ['zeile' => 5, 'email' => 'b@example.org', 'wert' => '02.01.1990'],
            ['zeile' => 6, 'email' => '', 'wert' => '02.01.1990'],
        ]);

        $this->assertSame(
            [Import::SETZEN, Import::DOPPELT, Import::KONFLIKT, Import::KONFLIKT, Import::UNGUELTIG],
            array_column($auswertung, 'status')
        );
    }

    /** @test */
    public function kopfzeile_wird_in_den_ersten_zeilen_gefunden_und_leere_zeilen_ignoriert(): void
    {
        $zeilen = app(Import::class)->zeilenAuslesen([
            ['Geburtstagsliste 2026', null],
            [null, null],
            ['Geburtstag', 'e-Mail'],
            ['01.01.1990', 'a@example.org'],
            [null, null],
            ['02.02.1990', 'b@example.org'],
        ]);

        $this->assertSame([
            ['zeile' => 4, 'email' => 'a@example.org', 'wert' => '01.01.1990'],
            ['zeile' => 6, 'email' => 'b@example.org', 'wert' => '02.02.1990'],
        ], $zeilen);
    }
}
