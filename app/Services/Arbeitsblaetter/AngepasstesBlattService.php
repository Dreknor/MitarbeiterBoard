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
            if ($blatt->quelle === 'wochenplan' && $blatt->ersteller) {
                $blatt->ersteller->notify(new AngepasstesBlattZurPruefung($blatt, true));
            }

            return $grund;
        }

        $blatt->update(['status' => AngepasstesBlatt::ERKENNUNG, 'fehler' => null]);
        AbBlattErkennen::dispatch($blatt->id, $user?->id);

        return null;
    }

    /** Wird vom Job aufgerufen: Struktur übernehmen, Abbildungen ausschneiden, verbuchen. */
    public function erkennungUebernehmen(AngepasstesBlatt $blatt, Ki\AbErkennung $erkennung, ?int $userId): void
    {
        $seiten = max(1, (int) ($blatt->ki_seiten ?: $erkennung->seiten));
        $this->budget->verbuchen($blatt->id, $userId ?? $blatt->erstellt_von, $seiten, $erkennung->kostenCent, $erkennung->anbieter);

        $struktur = $erkennung->struktur;
        $this->abbildungenAusschneiden($blatt, $struktur);

        // Hat jemand während der Erkennung schon getippt, nicht überschreiben
        $vorhanden = $blatt->fresh()->strukturGueltig();
        if (! empty($vorhanden['bausteine'])) {
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
            'fehler' => null,
            'version' => DB::raw('version + 1'),
            'updated_at' => now(),
        ]);

        $blatt->refresh();
        if ($blatt->ersteller) {
            $blatt->ersteller->notify(new AngepasstesBlattZurPruefung($blatt));
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
        if ($blatt->ersteller) {
            $blatt->ersteller->notify(new \App\Notifications\AngepasstesBlattFehler($blatt));
        }
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
        [$x1, $y1, $x2, $y2] = array_map('floatval', $box);
        $rechteck = [
            'x' => (int) floor($x1 * $b), 'y' => (int) floor($y1 * $h),
            'width' => max(1, (int) ceil(($x2 - $x1) * $b)), 'height' => max(1, (int) ceil(($y2 - $y1) * $h)),
        ];
        $ausschnitt = imagecrop($bild, $rechteck);
        imagedestroy($bild);
        if (! $ausschnitt) {
            return null;
        }
        ob_start();
        imagepng($ausschnitt, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($ausschnitt);

        return $png;
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
    public function abbildungSpeichern(AngepasstesBlatt $blatt, UploadedFile $datei): ?Media
    {
        $inhalt = (string) file_get_contents($datei->getRealPath());

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
                $blatt->ersteller?->notify(new \App\Notifications\AngepasstesBlattOffen($blatt, $tage));
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
