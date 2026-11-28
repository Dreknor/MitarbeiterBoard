<?php

namespace App\Services\Arbeitsblaetter;

use App\Exceptions\VersionKonfliktException;
use App\Jobs\Arbeitsblaetter\AbBlattErkennen;
use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Models\User;
use App\Notifications\AngepasstesBlattZurPruefung;
use App\Services\Arbeitsblaetter\Ki\AbErkenner;
use App\Support\AbStruktur;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Einzige Stelle für Anlegen (Kamera/Upload/Wochenplan/manuell), Erkennung, Speichern,
 * Freigabe, Duplizieren, Löschen und die Bibliothekssuche der Angepassten Arbeitsblätter.
 *
 * - Speichern prüft die `version` (optimistische Sperre → {@see VersionKonfliktException}).
 * - Jede Strukturänderung nach der Freigabe macht die Fassungen `veraltet`; erneutes
 *   Freigeben setzt die Fassungen im Wochenplan neu.
 */
class AngepasstesBlattService
{
    public function __construct(
        private AbQuellenService $quellen,
        private AbKiBudget $budget,
        private AbAusgabeService $ausgabe,
    ) {
    }

    // ─── Anlegen ──────────────────────────────────────────────────────────────

    /**
     * Neues Blatt aus Uploads (Kamera-Seiten oder Datei). Startet die Erkennung, wenn möglich.
     *
     * @param  array<int, UploadedFile>  $dateien
     *
     * @throws AbQuellenFehler
     */
    public function ausUploads(array $daten, array $dateien, string $quelle, User $user, bool $erkennen = true): AngepasstesBlatt
    {
        $eingang = $this->quellen->ausUploads($dateien);
        $eigen = trim((string) ($daten['titel'] ?? ''));
        $titel = $eigen ?: $this->titelAusDatei($dateien[0]?->getClientOriginalName() ?? '');

        return $this->anlegen(array_merge($daten, ['titel' => $titel, 'titel_automatisch' => $eigen === '', 'quelle' => $quelle]), $eingang, $user, $erkennen);
    }

    /** Neues Blatt aus vorhandenem Inhalt (z. B. Arbeitsblatt eines Wochenplans). */
    public function ausInhalt(array $daten, string $inhalt, string $dateiname, ?User $user, bool $erkennen = true): AngepasstesBlatt
    {
        $eingang = $this->quellen->ausInhalt($inhalt, $dateiname);
        $daten['titel_automatisch'] = $daten['titel_automatisch'] ?? true;
        $daten['titel'] = trim((string) ($daten['titel'] ?? '')) ?: $this->titelAusDatei($dateiname);

        return $this->anlegen($daten, $eingang, $user, $erkennen);
    }

    /** Leeres Blatt zum Abtippen (ohne Original). */
    public function leer(array $daten, User $user): AngepasstesBlatt
    {
        return AngepasstesBlatt::create([
            'titel' => trim((string) ($daten['titel'] ?? '')) ?: 'Neues Arbeitsblatt',
            'wp_fach_id' => $daten['wp_fach_id'] ?? null,
            'klassenstufe' => $daten['klassenstufe'] ?? null,
            'quelle' => 'manuell',
            'status' => AngepasstesBlatt::ENTWURF,
            'struktur' => AbStruktur::leer(),
            'erstellt_von' => $user->id,
        ]);
    }

    private function anlegen(array $daten, array $eingang, ?User $user, bool $erkennen): AngepasstesBlatt
    {
        $blatt = DB::transaction(function () use ($daten, $eingang, $user) {
            $blatt = AngepasstesBlatt::create([
                'titel' => mb_substr((string) $daten['titel'], 0, 200),
                'titel_automatisch' => (bool) ($daten['titel_automatisch'] ?? false),
                'wp_fach_id' => $daten['wp_fach_id'] ?? null,
                'klassenstufe' => $daten['klassenstufe'] ?? null,
                'quelle' => $daten['quelle'] ?? 'upload',
                'quelle_wp_datei_id' => $daten['quelle_wp_datei_id'] ?? null,
                'schnell_ziele' => $daten['schnell_ziele'] ?? null,
                'status' => AngepasstesBlatt::ENTWURF,
                'struktur' => AbStruktur::leer(),
                'ki_seiten' => $eingang['seitenzahl'] ?? null,
                'textebene' => $eingang['textebene'] ?? null,
                'erstellt_von' => $user?->id,
            ]);

            foreach ($eingang['seiten'] as $seite) {
                $blatt->addMediaFromString($seite['inhalt'])
                    ->usingFileName($seite['name'])
                    ->withCustomProperties(['mime' => $seite['mime']])
                    ->toMediaCollection(AngepasstesBlatt::ORIGINALE);
            }

            // DOCX ohne Umwandlung: Rohstruktur direkt übernehmen, Bilder als Abbildungen
            if ($eingang['art'] === 'struktur') {
                $struktur = $eingang['struktur'];
                foreach ($struktur['bausteine'] as &$b) {
                    if (($b['typ'] ?? '') === 'bild' && isset($b['abbildung'], $eingang['abbildungen'][$b['abbildung']])) {
                        $bild = $eingang['abbildungen'][$b['abbildung']];
                        $media = $this->abbildungAusInhalt($blatt, $bild['inhalt'], $bild['mime']);
                        $b['media_id'] = $media?->id;
                    }
                    unset($b['abbildung']);
                }
                unset($b);
                $blatt->update(['struktur' => AbStruktur::normalisieren($struktur), 'status' => AngepasstesBlatt::ZU_PRUEFEN]);
            }

            return $blatt;
        });

        if ($erkennen && $eingang['art'] !== 'struktur') {
            $this->erkennungStarten($blatt, $user);
        }

        return $blatt->fresh();
    }

    // ─── Erkennung ────────────────────────────────────────────────────────────

    /** Gründe, warum keine KI-Erkennung möglich ist (null = möglich). */
    public function erkennungGesperrt(AngepasstesBlatt $blatt): ?string
    {
        $erkenner = app(AbErkenner::class);
        if (! $erkenner->konfiguriert()) {
            return 'Es ist keine KI-Erkennung eingerichtet – das Blatt wird manuell erfasst.';
        }
        if ($blatt->getMedia(AngepasstesBlatt::ORIGINALE)->isEmpty()) {
            return 'Es gibt kein Original, das erkannt werden kann.';
        }

        return $this->budget->sperrgrund(max(1, (int) $blatt->ki_seiten));
    }

    /** Startet die Erkennung als Job. Liefert den Sperrgrund, wenn sie nicht möglich ist. */
    public function erkennungStarten(AngepasstesBlatt $blatt, ?User $user): ?string
    {
        $grund = $this->erkennungGesperrt($blatt);
        if ($grund !== null) {
            if ($blatt->quelle === 'wochenplan') {
                $this->benachrichtigen($blatt->ersteller, new AngepasstesBlattZurPruefung($blatt, true));
            }

            return $grund;
        }

        $blatt->update(['status' => AngepasstesBlatt::ERKENNUNG, 'fehler' => null]);
        $version = (int) $blatt->fresh()->version;
        if ($this->erkennungPerAnfrage()) {
            // Ohne Queue-Worker: nur den Auftrag merken – Upload bzw. „Erkennen“ antworten sofort. Die geöffnete
            // Seite stößt ihn mit einer eigenen Anfrage an (erkennungAusfuehren), sonst der Scheduler jede Minute.
            // („Nach der Antwort“ reicht nicht: unter mod_fcgid/php-cgi wartet der Browser trotzdem aufs Skriptende.)
            Cache::put(self::auftragSchluessel($blatt->id), ['user' => $user?->id, 'version' => $version], now()->addHours(2));
        } else {
            dispatch(new AbBlattErkennen($blatt->id, $user?->id, $version));
        }

        return null;
    }

    /** Ohne Queue-Worker im Webbetrieb wird die Erkennung per eigener Anfrage bzw. Scheduler ausgeführt. */
    public function erkennungPerAnfrage(): bool
    {
        $einstellung = config('arbeitsblaetter.ki.erkennung_per_anfrage');
        if ($einstellung !== null) {
            return (bool) $einstellung;
        }

        return config('queue.default') === 'sync' && ! app()->runningInConsole();
    }

    public static function auftragSchluessel(int $blattId): string
    {
        return 'arbeitsblaetter.erkennung-auftrag.'.$blattId;
    }

    /** Wartet das Blatt auf den Start der Erkennung (Auftrag gemerkt, noch nicht gestartet)? */
    public function erkennungWartet(AngepasstesBlatt $blatt): bool
    {
        return $blatt->status === AngepasstesBlatt::ERKENNUNG && Cache::has(self::auftragSchluessel($blatt->id));
    }

    /**
     * Gemerkten Erkennungsauftrag ausführen (aus der Anfrage der geöffneten Seite oder dem Scheduler).
     * Eine Sperre je Blatt verhindert doppelte Läufe; liefert true, wenn hier erkannt wurde.
     */
    public function erkennungAusfuehren(AngepasstesBlatt $blatt): bool
    {
        $schluessel = self::auftragSchluessel($blatt->id);
        $sperre = Cache::lock($schluessel.'.sperre', 900);
        if (! $sperre->get()) {
            return false;
        }
        try {
            $auftrag = Cache::pull($schluessel);
            if (! is_array($auftrag) || $blatt->fresh()?->status !== AngepasstesBlatt::ERKENNUNG) {
                return false;
            }
            AbBlattErkennen::dispatchSync($blatt->id, $auftrag['user'] ?? null, $auftrag['version'] ?? null);

            return true;
        } finally {
            $sperre->release();
        }
    }

    /** Scheduler (jede Minute): Aufträge, die keine geöffnete Seite angestoßen hat, ausführen. */
    public function ausstehendeErkennungenAusfuehren(): int
    {
        $anzahl = 0;
        AngepasstesBlatt::where('status', AngepasstesBlatt::ERKENNUNG)
            ->where('updated_at', '<', now()->subSeconds(30))
            ->get()
            ->each(function (AngepasstesBlatt $blatt) use (&$anzahl) {
                if (Cache::has(self::auftragSchluessel($blatt->id)) && $this->erkennungAusfuehren($blatt)) {
                    $anzahl++;
                }
            });

        return $anzahl;
    }

    /** Wird vom Job aufgerufen: Struktur übernehmen, Abbildungen ausschneiden, verbuchen. */
    public function erkennungUebernehmen(AngepasstesBlatt $blatt, Ki\AbErkennung $erkennung, ?int $userId, ?int $basisVersion = null): void
    {
        $seiten = max(1, (int) ($blatt->ki_seiten ?: $erkennung->seiten));
        $this->budget->verbuchen($blatt->id, $userId ?? $blatt->erstellt_von, $seiten, $erkennung->kostenCent, $erkennung->anbieter);

        $struktur = $erkennung->struktur;
        $this->abbildungenAusschneiden($blatt, $struktur);

        // Erneute Erkennung ersetzt die Bausteine. Hat jemand während der Erkennung gespeichert
        // (Version geändert), wird nichts überschrieben, sondern angehängt.
        $aktuell = $blatt->fresh();
        $vorhanden = $aktuell->strukturGueltig();
        $ersetzen = $basisVersion !== null && (int) $aktuell->version === $basisVersion;
        if (! $ersetzen && ! empty($vorhanden['bausteine'])) {
            $struktur['bausteine'] = array_merge($vorhanden['bausteine'], $struktur['bausteine']);
            $struktur = AbStruktur::normalisieren($struktur);
        }

        // Titel aus dem Dateinamen → erkannte Überschrift
        $titel = [];
        $erste = $struktur['bausteine'][0] ?? null;
        if ($blatt->fresh()->titel_automatisch && $erste && $erste['typ'] === 'ueberschrift' && trim($erste['text']) !== '') {
            $titel = ['titel' => mb_substr(trim($erste['text']), 0, 200), 'titel_automatisch' => false];
        }

        AngepasstesBlatt::whereKey($blatt->id)->update($titel + [
            'struktur' => json_encode($struktur),
            'status' => AngepasstesBlatt::ZU_PRUEFEN,
            'ki_anbieter' => $erkennung->anbieter,
            'ki_modell' => mb_substr($erkennung->modell, 0, 64),
            'ki_seiten' => $seiten,
            'ki_hinweise' => $erkennung->hinweise ? json_encode(array_values($erkennung->hinweise), JSON_UNESCAPED_UNICODE) : null,
            'fehler' => null,
            'version' => DB::raw('version + 1'),
            'updated_at' => now(),
        ]);

        $blatt->refresh();
        $this->benachrichtigen($blatt->ersteller, new AngepasstesBlattZurPruefung($blatt));
    }

    /**
     * Benachrichtigungen der Kategorie „arbeitsblaetter“ sind Opt-in: standardmäßig aus; nur wer
     * Push oder Mail selbst einschaltet, bekommt sie (dann auch in der Glocke). Offene Blätter
     * zeigt die Bibliothek ohnehin.
     */
    public function benachrichtigen(?User $user, \Illuminate\Notifications\Notification $nachricht): void
    {
        if ($user && app(\App\Services\Benachrichtigungen\BenachrichtigungsService::class)->istAktiviert($user, 'arbeitsblaetter')) {
            $user->notify($nachricht);
        }
    }

    /** Zwischenstand während der Erkennung (z. B. „überlastet, neuer Versuch …“) – Status bleibt `erkennung`. */
    public function erkennungHinweis(AngepasstesBlatt $blatt, string $hinweis): void
    {
        AngepasstesBlatt::whereKey($blatt->id)->where('status', AngepasstesBlatt::ERKENNUNG)
            ->update(['fehler' => mb_substr($hinweis, 0, 1000), 'updated_at' => now()]);
    }

    /** Erkennung abbrechen und selbst erfassen; ein später eintreffendes Ergebnis wird verworfen. */
    public function erkennungAbbrechen(AngepasstesBlatt $blatt): AngepasstesBlatt
    {
        if ($blatt->status === AngepasstesBlatt::ERKENNUNG) {
            Cache::forget(self::auftragSchluessel($blatt->id));
            $blatt->update([
                'status' => empty($blatt->strukturGueltig()['bausteine']) ? AngepasstesBlatt::ENTWURF : AngepasstesBlatt::ZU_PRUEFEN,
                'fehler' => null,
            ]);
        }

        return $blatt;
    }

    /**
     * Erkennungen ohne Lebenszeichen (z. B. verlorener Auftrag, abgestürzter Worker) nach
     * {@see HAENGT_NACH_MINUTEN} beenden, damit das Blatt nicht dauerhaft „Wird erkannt …“ zeigt.
     */
    public const HAENGT_NACH_MINUTEN = 15;

    public function haengendeErkennungenBeenden(?AngepasstesBlatt $nur = null): int
    {
        $anzahl = 0;
        AngepasstesBlatt::query()
            ->with('ersteller')
            ->where('status', AngepasstesBlatt::ERKENNUNG)
            ->where('updated_at', '<', now()->subMinutes(self::HAENGT_NACH_MINUTEN))
            ->when($nur, fn ($q) => $q->whereKey($nur->id))
            ->each(function (AngepasstesBlatt $blatt) use (&$anzahl) {
                $letzter = $blatt->fehler ? ' Letzte Meldung: '.$blatt->fehler : '';
                $this->erkennungFehlgeschlagen($blatt, 'Die Erkennung hat nicht rechtzeitig geantwortet.'.$letzter);
                $anzahl++;
            });

        return $anzahl;
    }

    /** Erkennung endgültig fehlgeschlagen: Status `fehler`, manueller Weg bleibt offen. */
    public function erkennungFehlgeschlagen(AngepasstesBlatt $blatt, string $meldung): void
    {
        $blatt->update(['status' => AngepasstesBlatt::FEHLER, 'fehler' => mb_substr($meldung, 0, 1000)]);
        $this->benachrichtigen($blatt->ersteller, new \App\Notifications\AngepasstesBlattFehler($blatt));
    }

    /**
     * Seiten für die Erkennung (Bilder bzw. PDF aus `originale`).
     *
     * @return array<int, array{inhalt: string, mime: string}>
     */
    public function seitenFuerErkennung(AngepasstesBlatt $blatt): array
    {
        return $blatt->getMedia(AngepasstesBlatt::ORIGINALE)->map(fn (Media $m) => [
            'inhalt' => (string) file_get_contents($m->getPath()),
            'mime' => (string) ($m->getCustomProperty('mime') ?: $m->mime_type),
        ])->values()->all();
    }

    /**
     * Schneidet für Bild-Bausteine mit Box die Grafik aus dem Seitenbild (nur bei Fotos/Bildern –
     * bei PDF-Originalen schneidet die Lehrkraft im Editor aus).
     */
    public function abbildungenAusschneiden(AngepasstesBlatt $blatt, array &$struktur): void
    {
        $seiten = $blatt->getMedia(AngepasstesBlatt::ORIGINALE)
            ->filter(fn (Media $m) => str_starts_with((string) ($m->getCustomProperty('mime') ?: $m->mime_type), 'image/'))
            ->values();
        if ($seiten->isEmpty() || ! function_exists('imagecreatefromstring')) {
            return;
        }

        $schneiden = function (array &$b) use ($blatt, $seiten) {
            if ($b['typ'] !== 'bild' || ! empty($b['media_id']) || empty($b['quelle']['box'])) {
                return;
            }
            $seite = $seiten->get(((int) ($b['quelle']['seite'] ?? 1)) - 1);
            if (! $seite) {
                return;
            }
            $png = $this->ausschneiden((string) file_get_contents($seite->getPath()), $b['quelle']['box']);
            if ($png) {
                $b['media_id'] = $this->abbildungAusInhalt($blatt, $png, 'image/png')?->id;
            }
        };

        foreach ($struktur['bausteine'] as &$b) {
            $schneiden($b);
            foreach ($b['kinder'] ?? [] as $i => $k) {
                $schneiden($b['kinder'][$i]);
            }
        }
        unset($b);
    }

    /** Relative Box [x1, y1, x2, y2] aus einem Bild ausschneiden → PNG. */
    public function ausschneiden(string $bildInhalt, array $box): ?string
    {
        $bild = @imagecreatefromstring($bildInhalt);
        if (! $bild) {
            return null;
        }
        $b = imagesx($bild);
        $h = imagesy($bild);
        // rundum 2 % der Seite mehr: KI-Boxen schneiden Motive oft knapp ab; der Feinschnitt entfernt den Überschuss
        [$x1, $y1, $x2, $y2] = array_map('floatval', $box);
        [$x1, $y1, $x2, $y2] = [max(0, $x1 - 0.02), max(0, $y1 - 0.02), min(1, $x2 + 0.02), min(1, $y2 + 0.02)];
        $rechteck = [
            'x' => (int) floor($x1 * $b), 'y' => (int) floor($y1 * $h),
            'width' => max(1, (int) ceil(($x2 - $x1) * $b)), 'height' => max(1, (int) ceil(($y2 - $y1) * $h)),
        ];
        $ausschnitt = imagecrop($bild, $rechteck);
        imagedestroy($bild);
        if (! $ausschnitt) {
            return null;
        }
        $ausschnitt = $this->feinschnittBild($ausschnitt);
        ob_start();
        imagepng($ausschnitt, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($ausschnitt);

        return $png;
    }

    /** Feinschnitt für automatisch ausgeschnittene Abbildungen (Inhalt als PNG/JPG). */
    public function feinschnitt(string $bildInhalt): string
    {
        $bild = @imagecreatefromstring($bildInhalt);
        if (! $bild) {
            return $bildInhalt;
        }
        $bild = $this->feinschnittBild($bild);
        ob_start();
        imagepng($bild, null, 6);
        imagedestroy($bild);

        return (string) ob_get_clean();
    }

    /**
     * Die Boxen der KI sind ungenau: Sie greifen oft Ränder der Nachbarbilder oder Beschriftungskästchen mit.
     * Kleine, durch einen weißen Streifen abgetrennte Teile an den Kanten werden abgeschnitten, danach der
     * weiße Rand. Analysiert wird auf einer verkleinerten Kopie (schnell), geschnitten im Original.
     */
    private function feinschnittBild(\GdImage $bild): \GdImage
    {
        $b = imagesx($bild);
        $h = imagesy($bild);
        if ($b < 12 || $h < 12) {
            return $bild;
        }
        $faktor = min(1, 300 / max($b, $h));
        $kb = max(1, (int) round($b * $faktor));
        $kh = max(1, (int) round($h * $faktor));
        $klein = imagecreatetruecolor($kb, $kh);
        imagefill($klein, 0, 0, imagecolorallocate($klein, 255, 255, 255));
        imagecopyresampled($klein, $bild, 0, 0, 0, 0, $kb, $kh, $b, $h);

        $zeilen = array_fill(0, $kh, 0);
        $spalten = array_fill(0, $kb, 0);
        for ($y = 0; $y < $kh; $y++) {
            for ($x = 0; $x < $kb; $x++) {
                $f = imagecolorat($klein, $x, $y);
                if (((($f >> 16) & 255) * 0.299 + (($f >> 8) & 255) * 0.587 + ($f & 255) * 0.114) < 200) {
                    $zeilen[$y]++;
                    $spalten[$x]++;
                }
            }
        }
        imagedestroy($klein);

        [$y1, $y2] = $this->hauptbereich($zeilen);
        [$x1, $x2] = $this->hauptbereich($spalten);
        if ($y2 < $y1 || $x2 < $x1) {
            return $bild; // leer – nichts zu schneiden
        }
        $rand = 2;
        $rechteck = [
            'x' => (int) max(0, floor(($x1 - $rand) / $faktor)),
            'y' => (int) max(0, floor(($y1 - $rand) / $faktor)),
        ];
        $rechteck['width'] = (int) min($b - $rechteck['x'], ceil(($x2 + $rand + 1) / $faktor) - $rechteck['x']);
        $rechteck['height'] = (int) min($h - $rechteck['y'], ceil(($y2 + $rand + 1) / $faktor) - $rechteck['y']);
        // nur schneiden, wenn es sich lohnt und das Motiv nicht zusammenschrumpft
        if ($rechteck['width'] * $rechteck['height'] >= $b * $h * 0.97 || $rechteck['width'] < $b * 0.25 || $rechteck['height'] < $h * 0.25) {
            return $bild;
        }
        $neu = imagecrop($bild, $rechteck);
        if (! $neu) {
            return $bild;
        }
        imagedestroy($bild);

        return $neu;
    }

    /**
     * Von-bis des Hauptinhalts in einem Tintenprofil: Streifen mit Tinte, getrennt durch leere Streifen;
     * kleine Streifen, die an der Kante kleben (Reste von Nachbarbildern), fallen weg.
     *
     * @param  list<int>  $profil
     * @return array{0: int, 1: int}
     */
    private function hauptbereich(array $profil): array
    {
        $baender = [];
        $start = null;
        foreach ($profil as $i => $tinte) {
            if ($tinte > 0 && $start === null) {
                $start = $i;
            }
            if (($tinte === 0 || $i === count($profil) - 1) && $start !== null) {
                $ende = $tinte === 0 ? $i - 1 : $i;
                $baender[] = [$start, $ende, array_sum(array_slice($profil, $start, $ende - $start + 1))];
                $start = null;
            }
        }
        if (! $baender) {
            return [1, 0];
        }
        $gesamt = max(1, array_sum(array_column($baender, 2)));
        $letzter = count($profil) - 1;
        // Randstreifen ist Fremdteil, wenn er wenig Tinte hat oder schmal ist (z. B. farbiger Beschriftungsbalken)
        $fremd = fn (array $band) => $band[2] < 0.15 * $gesamt || ($band[1] - $band[0] + 1) < 0.2 * count($profil);
        $kante = max(1, (int) round(0.08 * count($profil)));
        while (count($baender) > 1 && $baender[0][0] <= $kante && $fremd($baender[0])) {
            array_shift($baender);
        }
        while (count($baender) > 1 && end($baender)[1] >= $letzter - $kante && $fremd(end($baender))) {
            array_pop($baender);
        }

        return [$baender[0][0], end($baender)[1]];
    }

    // ─── Bearbeiten ───────────────────────────────────────────────────────────

    /** @throws VersionKonfliktException */
    public function versionPruefen(AngepasstesBlatt $blatt, mixed $version): void
    {
        if ($version === null || $version === '') {
            return;
        }
        $aktuell = (int) AngepasstesBlatt::withTrashed()->whereKey($blatt->id)->value('version');
        if ((int) $version !== $aktuell) {
            throw new VersionKonfliktException('Das Blatt wurde zwischenzeitlich geändert.');
        }
    }

    /**
     * Speichert Struktur und Kopfdaten.
     *
     * @throws VersionKonfliktException
     */
    public function speichern(AngepasstesBlatt $blatt, ?array $struktur, mixed $version, array $kopf = []): AngepasstesBlatt
    {
        return DB::transaction(function () use ($blatt, $struktur, $version, $kopf) {
            $blatt = AngepasstesBlatt::whereKey($blatt->id)->lockForUpdate()->firstOrFail();
            $this->versionPruefen($blatt, $version);

            $aenderungen = [];
            foreach (['titel', 'wp_fach_id', 'klassenstufe'] as $feld) {
                if (array_key_exists($feld, $kopf)) {
                    $aenderungen[$feld] = $feld === 'titel' ? (mb_substr(trim((string) $kopf[$feld]), 0, 200) ?: $blatt->titel) : ($kopf[$feld] ?: null);
                }
            }

            $strukturGeaendert = false;
            if ($struktur !== null) {
                $neu = AbStruktur::normalisieren($struktur);
                $strukturGeaendert = $neu !== $blatt->strukturGueltig();
                $aenderungen['struktur'] = $neu;
            }
            $titelGeaendert = isset($aenderungen['titel']) && $aenderungen['titel'] !== $blatt->titel;
            if ($titelGeaendert) {
                $aenderungen['titel_automatisch'] = false;
            }

            if ($blatt->status === AngepasstesBlatt::ENTWURF && $strukturGeaendert) {
                $aenderungen['status'] = AngepasstesBlatt::ZU_PRUEFEN;
            }

            $blatt->fill($aenderungen);
            $blatt->version = (int) $blatt->version + 1;
            $blatt->save();

            if (($strukturGeaendert || $titelGeaendert) && $blatt->istFreigegeben()) {
                $this->ausgabe->blattVeraltet($blatt);
            }

            return $blatt;
        });
    }

    /** Freigabe nach der Prüfung; im Wochenplan entstehen danach die Fassungen der Profilkinder. */
    public function freigeben(AngepasstesBlatt $blatt, User $user): AngepasstesBlatt
    {
        if (empty($blatt->strukturGueltig()['bausteine'])) {
            throw new AbQuellenFehler('Ein leeres Blatt kann nicht freigegeben werden.');
        }

        $blatt->update([
            'status' => AngepasstesBlatt::FREIGEGEBEN,
            'geprueft_von' => $user->id,
            'freigegeben_at' => now(),
            'fehler' => null,
            // Schnellmodus gibt ohne Prüfung frei – Hinweise der Erkennung bleiben dort sichtbar
            'ki_hinweise' => $blatt->schnell_ziele ? $blatt->ki_hinweise : null,
        ]);

        if ($blatt->quelle_wp_datei_id) {
            app(AbWochenplanService::class)->fassungenVerteilenAnfordern($blatt, $user);
        }

        return $blatt;
    }

    /** Kopie inkl. Originale und Abbildungen (Media-IDs in der Struktur umgeschrieben). */
    public function duplizieren(AngepasstesBlatt $blatt, User $user): AngepasstesBlatt
    {
        return DB::transaction(function () use ($blatt, $user) {
            $kopie = $blatt->replicate(['quelle_wp_datei_id', 'freigegeben_at', 'geprueft_von', 'erinnert_at', 'fehler']);
            $kopie->titel = mb_substr($blatt->titel.' (Kopie)', 0, 200);
            $kopie->status = AngepasstesBlatt::ZU_PRUEFEN;
            $kopie->quelle = $blatt->quelle === 'wochenplan' ? 'upload' : $blatt->quelle;
            $kopie->erstellt_von = $user->id;
            $kopie->version = 1;
            $kopie->save();

            foreach ($blatt->getMedia(AngepasstesBlatt::ORIGINALE) as $media) {
                $media->copy($kopie, AngepasstesBlatt::ORIGINALE, 'arbeitsblaetter');
            }
            $zuordnung = [];
            foreach ($blatt->getMedia(AngepasstesBlatt::ABBILDUNGEN) as $media) {
                $zuordnung[$media->id] = $media->copy($kopie, AngepasstesBlatt::ABBILDUNGEN, 'arbeitsblaetter')->id;
            }

            $struktur = $blatt->strukturGueltig();
            $umschreiben = function (array &$b) use ($zuordnung) {
                if ($b['typ'] === 'bild' && ! empty($b['media_id'])) {
                    $b['media_id'] = $zuordnung[$b['media_id']] ?? null;
                }
            };
            foreach ($struktur['bausteine'] as &$b) {
                $umschreiben($b);
                foreach ($b['kinder'] ?? [] as $i => $k) {
                    $umschreiben($b['kinder'][$i]);
                }
            }
            unset($b);
            $kopie->update(['struktur' => $struktur]);

            return $kopie;
        });
    }

    /** Papierkorb (Soft-Delete); Fassungen im Wochenplan bleiben bestehen. */
    public function loeschen(AngepasstesBlatt $blatt): void
    {
        $blatt->delete();
    }

    // ─── Abbildungen ──────────────────────────────────────────────────────────

    /** Im Browser ausgeschnittene Abbildung (PNG/JPEG) speichern. */
    /** @param  bool  $feinschnitt  automatisch aus einer KI-Box geschnitten → Ränder/Nachbarreste entfernen */
    public function abbildungSpeichern(AngepasstesBlatt $blatt, UploadedFile $datei, bool $feinschnitt = false): ?Media
    {
        $inhalt = (string) file_get_contents($datei->getPathname());
        if ($feinschnitt) {
            return $this->abbildungAusInhalt($blatt, $this->feinschnitt($inhalt), 'image/png');
        }

        return $this->abbildungAusInhalt($blatt, $inhalt, (string) $datei->getMimeType());
    }

    public function abbildungAusInhalt(AngepasstesBlatt $blatt, string $inhalt, string $mime): ?Media
    {
        try {
            // neu kodieren: entfernt Metadaten und prüft, dass es wirklich ein Bild ist
            $bild = @imagecreatefromstring($inhalt);
            if (! $bild) {
                return null;
            }
            imagesavealpha($bild, true);
            ob_start();
            imagepng($bild, null, 6);
            $png = (string) ob_get_clean();
            imagedestroy($bild);

            return $blatt->addMediaFromString($png)
                ->usingFileName('abbildung-'.uniqid().'.png')
                ->withCustomProperties(['mime' => 'image/png'])
                ->toMediaCollection(AngepasstesBlatt::ABBILDUNGEN);
        } catch (\Throwable $e) {
            Log::warning('Abbildung konnte nicht gespeichert werden', ['blatt' => $blatt->id, 'fehler' => $e->getMessage(), 'mime' => $mime]);

            return null;
        }
    }

    /**
     * Abbildungen, die von keinem Baustein mehr genutzt werden und älter als einen Tag sind, löschen.
     * Liefert die Anzahl.
     */
    public function verwaisteAbbildungenBereinigen(): int
    {
        $anzahl = 0;
        Media::query()
            ->where('model_type', (new AngepasstesBlatt())->getMorphClass())
            ->where('collection_name', AngepasstesBlatt::ABBILDUNGEN)
            ->where('created_at', '<', now()->subDay())
            ->orderBy('model_id')
            ->get()
            ->groupBy('model_id')
            ->each(function ($medien, $blattId) use (&$anzahl) {
                $blatt = AngepasstesBlatt::withTrashed()->find($blattId);
                $genutzt = $blatt && ! $blatt->trashed() ? AbStruktur::mediaIds($blatt->strukturGueltig()) : [];
                if ($blatt && $blatt->trashed()) {
                    return; // Papierkorb: nichts anfassen
                }
                foreach ($medien as $media) {
                    if (! in_array((int) $media->id, $genutzt, true)) {
                        $media->delete();
                        $anzahl++;
                    }
                }
            });

        return $anzahl;
    }

    // ─── Bibliothek ───────────────────────────────────────────────────────────

    /**
     * Filter: q (Titel), fach, klassenstufe, ansicht (alle|meine|zur_pruefung|freigegeben).
     */
    public function bibliothek(User $user, array $filter, int $proSeite = 24): LengthAwarePaginator
    {
        $query = AngepasstesBlatt::query()->with(['fach', 'ersteller'])->withCount('fassungen');

        if (($q = trim((string) ($filter['q'] ?? ''))) !== '') {
            $query->where('titel', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%');
        }
        if (! empty($filter['fach'])) {
            $query->where('wp_fach_id', (int) $filter['fach']);
        }
        if (! empty($filter['klassenstufe'])) {
            $query->where('klassenstufe', (int) $filter['klassenstufe']);
        }
        match ($filter['ansicht'] ?? 'alle') {
            'meine' => $query->where('erstellt_von', $user->id),
            'zur_pruefung' => $query->where('erstellt_von', $user->id)->whereIn('status', [AngepasstesBlatt::ZU_PRUEFEN, AngepasstesBlatt::FEHLER, AngepasstesBlatt::ENTWURF, AngepasstesBlatt::ERKENNUNG]),
            'freigegeben' => $query->where('status', AngepasstesBlatt::FREIGEGEBEN),
            default => null,
        };

        return $query->orderByDesc('updated_at')->paginate($proSeite)->withQueryString();
    }

    /** Zahl der eigenen Blätter, die auf Prüfung warten (Badge). */
    public function offeneFuer(User $user): int
    {
        return AngepasstesBlatt::where('erstellt_von', $user->id)
            ->whereIn('status', [AngepasstesBlatt::ZU_PRUEFEN, AngepasstesBlatt::FEHLER])
            ->count();
    }

    /** Täglich: Entwürfe/Blätter ohne Freigabe nach N Tagen melden (nicht löschen). */
    public function offeneEntwuerfeMelden(): int
    {
        $tage = (int) config('arbeitsblaetter.entwurf_erinnerung_tage', 90);
        $anzahl = 0;
        AngepasstesBlatt::query()
            ->with('ersteller')
            ->whereIn('status', [AngepasstesBlatt::ENTWURF, AngepasstesBlatt::ZU_PRUEFEN, AngepasstesBlatt::FEHLER])
            ->where('updated_at', '<', now()->subDays($tage))
            ->whereNull('erinnert_at')
            ->each(function (AngepasstesBlatt $blatt) use ($tage, &$anzahl) {
                $this->benachrichtigen($blatt->ersteller, new \App\Notifications\AngepasstesBlattOffen($blatt, $tage));
                AngepasstesBlatt::whereKey($blatt->id)->update(['erinnert_at' => now()]);
                $anzahl++;
            });

        return $anzahl;
    }

    private function titelAusDatei(string $name): string
    {
        $titel = trim(str_replace(['_', '-'], ' ', pathinfo($name, PATHINFO_FILENAME)));
        if ($titel === '' || preg_match('/^(img|image|dsc|pxl|photo|foto|seite)?\s*\d*$/i', $titel)) {
            return 'Arbeitsblatt vom '.now()->format('d.m.Y');
        }

        return mb_substr($titel, 0, 200);
    }
}
