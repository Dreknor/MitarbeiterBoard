<?php

namespace Tests\Traits;

use App\Models\Arbeitsblaetter\AbProfilVorlage;
use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\Setting;
use App\Models\User;
use App\Services\Arbeitsblaetter\AbKonvertierung;
use App\Services\Arbeitsblaetter\AbNextcloudSpeicher;
use App\Services\Arbeitsblaetter\Ki\AbErkenner;
use App\Services\Arbeitsblaetter\Ki\FakeErkenner;
use App\Services\Arbeitsblaetter\SehprofilService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Fakes\AbKonvertierungFake;
use Tests\Fakes\AbNextcloudFake;

trait ArbeitsblaetterTestHilfen
{
    protected ?FakeErkenner $erkenner = null;

    protected ?AbNextcloudFake $abNextcloud = null;

    protected ?AbKonvertierungFake $konvertierung = null;

    protected function arbeitsblaetterEinrichten(bool $ki = true): void
    {
        Storage::fake('arbeitsblaetter');
        Storage::fake('wochenplan');
        $this->erkenner = new FakeErkenner();
        if ($ki) {
            $this->app->instance(AbErkenner::class, $this->erkenner);
        } else {
            $this->app->instance(AbErkenner::class, new \App\Services\Arbeitsblaetter\Ki\KeinErkenner());
        }
        $this->abNextcloud = new AbNextcloudFake();
        $this->app->instance(AbNextcloudSpeicher::class, $this->abNextcloud);
        $this->konvertierung = new AbKonvertierungFake();
        $this->app->instance(AbKonvertierung::class, $this->konvertierung);
    }

    protected function lehrkraft(array $rechte = ['create adapted worksheets'], array $klassen = []): User
    {
        $user = User::factory()->create();
        foreach ($rechte as $recht) {
            Permission::findOrCreate($recht);
        }
        $user->givePermissionTo($rechte);
        foreach ($klassen as $klasse) {
            $user->paed_klassen()->attach($klasse->id);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $user;
    }

    protected function blatt(array $werte = [], ?array $bausteine = null): AngepasstesBlatt
    {
        return AngepasstesBlatt::create(array_merge([
            'titel' => 'Brüche',
            'quelle' => 'manuell',
            'status' => AngepasstesBlatt::FREIGEGEBEN,
            'struktur' => ['seiten' => 1, 'bausteine' => $bausteine ?? [
                ['typ' => 'ueberschrift', 'text' => 'Brüche üben'],
                ['typ' => 'aufgabe', 'nummer' => '1', 'inhalt' => [['typ' => 'text', 'text' => '3/4 = '], ['typ' => 'luecke', 'laenge' => 4]]],
                ['typ' => 'ankreuzen', 'frage' => 'Stimmt das?', 'optionen' => ['ja', 'nein']],
                ['typ' => 'schreiblinien', 'anzahl' => 2],
            ]],
        ], $werte));
    }

    protected function kindMitProfil(?Klasse $klasse = null, array $werte = [], ?string $vorlage = 'Sehr groß'): Schueler
    {
        $klasse ??= Klasse::factory()->create();
        $kind = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $vorlageId = $vorlage ? AbProfilVorlage::where('name', $vorlage)->value('id') : null;
        app(SehprofilService::class)->speichern($kind, $vorlageId, $werte, true, null);

        return $kind->fresh();
    }

    protected function abEinstellung(string $schluessel, string $wert): void
    {
        Setting::updateOrCreate(['setting' => $schluessel], [
            'module' => 'Angepasste Arbeitsblätter',
            'setting_name' => $schluessel,
            'type' => 'string',
            'value' => $wert,
        ]);
        Cache::forget('setting_'.$schluessel);
    }

    /** Kleines PNG/JPEG für Uploads. */
    protected function bildInhalt(int $breite = 400, int $hoehe = 560, string $art = 'jpeg'): string
    {
        $bild = imagecreatetruecolor($breite, $hoehe);
        imagefill($bild, 0, 0, imagecolorallocate($bild, 255, 255, 255));
        imagefilledrectangle($bild, 40, 200, 200, 400, imagecolorallocate($bild, 0, 0, 0));
        ob_start();
        $art === 'png' ? imagepng($bild) : imagejpeg($bild, null, 90);
        $inhalt = (string) ob_get_clean();
        imagedestroy($bild);

        return $inhalt;
    }
}
