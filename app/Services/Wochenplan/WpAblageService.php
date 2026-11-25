<?php

namespace App\Services\Wochenplan;

use App\Jobs\Wochenplan\WpDateiHochladen;
use App\Jobs\Wochenplan\WpPlanAblegen;
use App\Models\Wochenplan\WpDatei;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\Nextcloud\WpNextcloudSpeicher;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Zentrale Ablage des Wochenplans in der Nextcloud.
 *
 * - Ordner und Dateinamen entstehen aus den Vorlagen in den Einstellungen
 *   (`wochenplan_ordner_vorlage`, `wochenplan_datei_vorlage`, `wochenplan_kinderplan_vorlage`).
 * - Jede Änderung fordert eine Ablage an ({@see anfordern()}); ein Job legt den Plan
 *   2 Minuten nach der letzten Änderung als PDF (ohne Arbeitsblätter) und Word ab und
 *   überschreibt die vorhandenen Dateien.
 * - Ändert sich der Zielordner (Name, Zeitraum, Klasse, Vorlage), wird der Ordner verschoben;
 *   gelöschte Pläne/Dateien wandern nach `_gelöscht/`.
 * - Arbeitsblätter liegen zuerst im Zwischenspeicher (Disk `wochenplan`) und werden per Job
 *   hochgeladen; ohne aktive Ablage bleiben sie dort (`status = lokal`).
 */
class WpAblageService
{
    public const VERZOEGERUNG_SEKUNDEN = 120;

    public const GELOESCHT_ORDNER = '_gelöscht';

    public const PLATZHALTER = ['schuljahr', 'klasse', 'jahr', 'kw', 'von', 'bis', 'plan'];

    public const PLATZHALTER_KIND = ['vorname', 'nachname'];

    public const STANDARD_VORLAGEN = [
        'ordner' => '{schuljahr}/{klasse}/{jahr}-KW{kw} {plan}',
        'datei' => '{plan}',
        'kinderplan' => 'Kinderpläne/{nachname}, {vorname}',
    ];

    public function __construct(private WpNextcloudSpeicher $nextcloud)
    {
    }

    public function aktiv(): bool
    {
        return $this->nextcloud->aktiv();
    }

    public function speicher(): WpNextcloudSpeicher
    {
        return $this->nextcloud;
    }

    // ─── Vorlagen ─────────────────────────────────────────────────────────────

    /** @return array{ordner: string, datei: string, kinderplan: string} */
    public function vorlagen(): array
    {
        return [
            'ordner' => (string) (settings('wochenplan_ordner_vorlage') ?: self::STANDARD_VORLAGEN['ordner']),
            'datei' => (string) (settings('wochenplan_datei_vorlage') ?: self::STANDARD_VORLAGEN['datei']),
            'kinderplan' => (string) (settings('wochenplan_kinderplan_vorlage') ?: self::STANDARD_VORLAGEN['kinderplan']),
        ];
    }

    /** Prüft eine Vorlage; liefert die Fehlermeldung oder null. */
    public function vorlageFehler(string $vorlage, bool $mitKind = false): ?string
    {
        $vorlage = trim($vorlage);
        if ($vorlage === '') {
            return 'Die Vorlage darf nicht leer sein.';
        }
        if (mb_strlen($vorlage) > 255) {
            return 'Die Vorlage ist zu lang (max. 255 Zeichen).';
        }
        if (preg_match('/[\\\\:*?"<>|\x00-\x1F]/u', $vorlage)) {
            return 'Die Vorlage enthält unzulässige Zeichen (\\ : * ? " < > |).';
        }
        foreach (explode('/', $vorlage) as $teil) {
            if (in_array(trim($teil), ['.', '..'], true)) {
                return 'Die Vorlage darf keine Ordner „.“ oder „..“ enthalten.';
            }
        }
        if (str_starts_with($vorlage, '/')) {
            return 'Die Vorlage muss relativ sein (ohne führendes /).';
        }
        $erlaubt = $mitKind ? array_merge(self::PLATZHALTER, self::PLATZHALTER_KIND) : self::PLATZHALTER;
        preg_match_all('/\{([^}]*)\}/', $vorlage, $treffer);
        foreach ($treffer[1] as $name) {
            if (! in_array($name, $erlaubt, true)) {
                return 'Unbekannter Platzhalter {'.$name.'}. Erlaubt: {'.implode('} {', $erlaubt).'}.';
            }
        }
        if (substr_count($vorlage, '{') !== substr_count($vorlage, '}')) {
            return 'Geschweifte Klammern sind nicht vollständig.';
        }

        return null;
    }

    /** Werte der Platzhalter für einen Plan (bzw. für Beispieldaten in der Vorschau). */
    public function platzhalterWerte(WpPlan $plan): array
    {
        $von = $plan->gueltig_von ? Carbon::parse($plan->gueltig_von) : now();
        $bis = $plan->gueltig_bis ? Carbon::parse($plan->gueltig_bis) : $von->copy();
        $schuljahrStart = $von->month >= 8 ? $von->year : $von->year - 1;

        $klasse = $plan->isSchuelerplan()
            ? ($plan->schueler?->klasse?->name ?? $plan->parentPlan?->klasse?->name)
            : $plan->klasse?->name;

        $name = $plan->isSchuelerplan() && $plan->parentPlan ? $plan->parentPlan->name : $plan->name;

        return [
            'schuljahr' => $schuljahrStart.'-'.substr((string) ($schuljahrStart + 1), -2),
            'klasse' => $klasse ?: 'Ohne Klasse',
            'jahr' => (string) $von->isoWeekYear(),
            'kw' => str_pad((string) $von->isoWeek(), 2, '0', STR_PAD_LEFT),
            'von' => $von->format('Y-m-d'),
            'bis' => $bis->format('Y-m-d'),
            'plan' => $name ?: 'Wochenplan',
            'vorname' => $plan->schueler?->vorname ?? '',
            'nachname' => $plan->schueler?->nachname ?? '',
        ];
    }

    /** Setzt die Werte in eine Vorlage ein und bereinigt jeden Pfadteil. */
    public function einsetzen(string $vorlage, array $werte): string
    {
        $ergebnis = preg_replace_callback('/\{([a-z]+)\}/', fn ($m) => (string) ($werte[$m[1]] ?? ''), $vorlage);

        $teile = array_map(fn ($teil) => $this->bereinigen($teil), explode('/', (string) $ergebnis));
        $teile = array_values(array_filter($teile, fn ($t) => $t !== ''));

        return implode('/', $teile) ?: 'Wochenplan';
    }

    /** Ein Pfadteil ohne unzulässige Zeichen. */
    public function bereinigen(string $teil): string
    {
        $teil = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', '-', $teil);
        $teil = trim(preg_replace('/\s+/u', ' ', (string) $teil), " .\t");

        return in_array($teil, ['.', '..'], true) ? '' : mb_substr($teil, 0, 150);
    }

    /**
     * Ziel eines Plans: Ordner (relativ zum Basisordner) und Dateiname ohne Endung
     * (relativ zum Ordner; bei Kinderplänen ggf. mit Unterordner).
     *
     * @return array{ordner: string, datei: string}
     */
    public function ziel(WpPlan $plan): array
    {
        $vorlagen = $this->vorlagen();
        $plan->loadMissing(['klasse', 'schueler.klasse', 'parentPlan.klasse']);
        $werte = $this->platzhalterWerte($plan);

        if ($plan->isSchuelerplan()) {
            if ($plan->parentPlan) {
                $ordner = $plan->parentPlan->ablage_ordner ?: $this->ziel($plan->parentPlan)['ordner'];
            } else {
                $ordner = $this->einsetzen($vorlagen['ordner'], array_merge($werte, ['plan' => 'Einzelpläne']));
            }

            return ['ordner' => $ordner, 'datei' => $this->einsetzen($vorlagen['kinderplan'], $werte)];
        }

        $ordner = $this->einsetzen($vorlagen['ordner'], $werte);

        // Kollision mit einem anderen Plan (gleicher Name, gleiche Woche, gleiche Klasse) vermeiden
        $belegt = WpPlan::query()
            ->whereNull('schueler_id')
            ->where('id', '!=', $plan->id)
            ->where('ablage_ordner', $ordner)
            ->exists();
        if ($belegt) {
            $ordner .= ' ('.$plan->id.')';
        }

        return ['ordner' => $ordner, 'datei' => $this->einsetzen($vorlagen['datei'], $werte)];
    }

    /** Ordner für die Arbeitsblätter eines Plans. */
    public function dateiOrdner(WpPlan $plan): string
    {
        $ordner = $plan->ablage_ordner ?: $this->ziel($plan)['ordner'];

        if ($plan->isSchuelerplan()) {
            $datei = $plan->ablage_datei ?: $this->ziel($plan)['datei'];

            return $ordner.'/'.$datei.' – Arbeitsblätter';
        }

        return $ordner.'/Arbeitsblätter';
    }

    // ─── Plan-Ablage ──────────────────────────────────────────────────────────

    /**
     * Fordert die Ablage eines Plans an (nach jeder Änderung). Der Job läuft erst
     * {@see VERZOEGERUNG_SEKUNDEN} nach der letzten Anforderung.
     */
    public function anfordern(WpPlan $plan): void
    {
        if ($plan->isVorlage() || $plan->trashed() || ! $this->aktiv()) {
            return;
        }

        $zeitpunkt = now();
        WpPlan::withTrashed()->whereKey($plan->id)->update([
            'ablage_angefordert_at' => $zeitpunkt,
            'ablage_status' => 'ausstehend',
        ]);
        $plan->ablage_angefordert_at = $zeitpunkt;
        $plan->ablage_status = 'ausstehend';

        WpPlanAblegen::dispatch($plan->id, $zeitpunkt->toIso8601String())
            ->delay(now()->addSeconds(self::VERZOEGERUNG_SEKUNDEN));
    }

    /** Legt PDF und Word eines Plans ab (verschiebt vorher bei geändertem Ziel). */
    public function ablegen(WpPlan $plan): bool
    {
        if ($plan->isVorlage() || ! $this->aktiv()) {
            return false;
        }

        $plan->load([
            'planFaecher.aufgaben', 'planFaecher.fach', 'taeglicheUebungen',
            'klasse', 'schueler.klasse', 'formatvorlage', 'parentPlan',
        ]);

        try {
            $ziel = $this->zielUebernehmen($plan);

            $pdf = app(WpPdfService::class)->generate($plan)->output();
            $wordPfad = app(WpWordService::class)->generate($plan);
            $word = (string) file_get_contents($wordPfad);
            @unlink($wordPfad);

            $basis = $ziel['ordner'].'/'.$ziel['datei'];
            $ok = $this->nextcloud->hochladen($pdf, $basis.'.pdf')
                && $this->nextcloud->hochladen($word, $basis.'.docx');

            if (! $ok) {
                throw new \RuntimeException('Upload in die Nextcloud fehlgeschlagen.');
            }

            WpPlan::withTrashed()->whereKey($plan->id)->update([
                'ablage_status' => 'abgelegt',
                'ablage_fehler' => null,
                'abgelegt_at' => now(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Wochenplan-Ablage fehlgeschlagen', ['plan' => $plan->id, 'fehler' => $e->getMessage()]);
            WpPlan::withTrashed()->whereKey($plan->id)->update([
                'ablage_status' => 'fehler',
                'ablage_fehler' => Str::limit($e->getMessage(), 500),
            ]);

            return false;
        }
    }

    /**
     * Übernimmt das aktuelle Ziel: verschiebt vorhandene Ordner/Dateien, wenn sich das
     * Ziel geändert hat, und speichert es am Plan.
     *
     * @return array{ordner: string, datei: string}
     */
    public function zielUebernehmen(WpPlan $plan): array
    {
        $ziel = $this->ziel($plan);
        $altOrdner = $plan->ablage_ordner;
        $altDatei = $plan->ablage_datei;

        if (! $plan->isSchuelerplan() || ! $plan->parent_plan_id) {
            if ($altOrdner && $altOrdner !== $ziel['ordner'] && $this->nextcloud->existiert($altOrdner)) {
                if (! $this->nextcloud->verschieben($altOrdner, $ziel['ordner'])) {
                    throw new \RuntimeException('Ordner konnte nicht verschoben werden: '.$altOrdner);
                }
                $this->praefixErsetzen($plan, $altOrdner, $ziel['ordner']);
            }
            if ($altOrdner && $altDatei && $altDatei !== $ziel['datei']) {
                $this->planDateienVerschieben($ziel['ordner'], $altDatei, $ziel['datei']);
            }
        } elseif ($altDatei && $altDatei !== $ziel['datei']) {
            // Kinderplan: Ordner gehört dem Klassenplan, nur die eigenen Dateien verschieben
            $ordner = $altOrdner ?: $ziel['ordner'];
            $this->planDateienVerschieben($ordner, $altDatei, $ziel['datei']);
            $altArbeit = $ordner.'/'.$altDatei.' – Arbeitsblätter';
            $neuArbeit = $ziel['ordner'].'/'.$ziel['datei'].' – Arbeitsblätter';
            if ($this->nextcloud->existiert($altArbeit)) {
                $this->nextcloud->verschieben($altArbeit, $neuArbeit);
            }
            WpDatei::where('wp_plan_id', $plan->id)->get()->each(function (WpDatei $d) use ($altArbeit, $neuArbeit) {
                if ($d->nextcloud_pfad && str_starts_with($d->nextcloud_pfad, $altArbeit.'/')) {
                    $d->update(['nextcloud_pfad' => $neuArbeit.substr($d->nextcloud_pfad, strlen($altArbeit))]);
                }
            });
        }

        WpPlan::withTrashed()->whereKey($plan->id)->update([
            'ablage_ordner' => $ziel['ordner'],
            'ablage_datei' => $ziel['datei'],
        ]);
        $plan->ablage_ordner = $ziel['ordner'];
        $plan->ablage_datei = $ziel['datei'];

        return $ziel;
    }

    /** Verschiebt den Ordner eines gelöschten Plans (bzw. die Dateien eines Kinderplans) nach `_gelöscht/`. */
    public function geloeschtAblegen(WpPlan $plan): void
    {
        if (! $this->aktiv() || ! $plan->ablage_ordner) {
            return;
        }

        $stempel = now()->format('Y-m-d His');

        if ($plan->isSchuelerplan() && $plan->parent_plan_id) {
            $datei = $plan->ablage_datei ?: $this->ziel($plan)['datei'];
            foreach (['.pdf', '.docx'] as $endung) {
                $von = $plan->ablage_ordner.'/'.$datei.$endung;
                if ($this->nextcloud->existiert($von)) {
                    $this->nextcloud->verschieben($von, self::GELOESCHT_ORDNER.'/'.$stempel.' '.basename($datei).$endung);
                }
            }
            $arbeit = $plan->ablage_ordner.'/'.$datei.' – Arbeitsblätter';
            if ($this->nextcloud->existiert($arbeit)) {
                $this->nextcloud->verschieben($arbeit, self::GELOESCHT_ORDNER.'/'.$stempel.' '.basename($arbeit));
            }

            return;
        }

        if ($this->nextcloud->existiert($plan->ablage_ordner)) {
            $this->nextcloud->verschieben($plan->ablage_ordner, self::GELOESCHT_ORDNER.'/'.$stempel.' '.$this->bereinigen(str_replace('/', ' – ', $plan->ablage_ordner)));
        }
    }

    /** Alle Pläne neu ablegen (z. B. nach geänderter Ordnerstruktur). Liefert die Anzahl. */
    public function alleNeuAblegen(): int
    {
        if (! $this->aktiv()) {
            return 0;
        }

        $anzahl = 0;
        // Klassenpläne zuerst, damit Kinderpläne den neuen Ordner ihres Klassenplans finden
        WpPlan::where('is_vorlage', false)->orderByRaw('schueler_id IS NOT NULL')->orderBy('id')
            ->each(function (WpPlan $plan) use (&$anzahl) {
                $this->anfordern($plan);
                $anzahl++;
            });

        return $anzahl;
    }

    // ─── Arbeitsblätter ───────────────────────────────────────────────────────

    public function dateiSpeichern(WpPlan $plan, UploadedFile $datei, ?int $userId): WpDatei
    {
        $name = $datei->getClientOriginalName();
        $dateiname = $this->bereinigen($name) ?: 'datei';
        $lokal = $datei->storeAs((string) $plan->id, Str::uuid().'_'.$dateiname, 'wochenplan');

        return $this->dateiAnlegen($plan, $name, $dateiname, $lokal, $datei->getMimeType(), (int) $datei->getSize(), $userId);
    }

    /** Legt eine Datei aus vorhandenem Inhalt an (Umzug, Kopie). */
    public function dateiAusInhalt(WpPlan $plan, string $name, string $inhalt, ?string $mime, ?int $userId): WpDatei
    {
        $dateiname = $this->bereinigen($name) ?: 'datei';
        $lokal = $plan->id.'/'.Str::uuid().'_'.$dateiname;
        Storage::disk('wochenplan')->put($lokal, $inhalt);

        return $this->dateiAnlegen($plan, $name, $dateiname, $lokal, $mime, strlen($inhalt), $userId);
    }

    /** Verknüpft eine bereits in der Nextcloud vorhandene Datei (ohne Upload). */
    public function nextcloudDateiVerknuepfen(WpPlan $plan, array $eintrag, ?int $userId): WpDatei
    {
        return WpDatei::create([
            'wp_plan_id' => $plan->id,
            'name' => $eintrag['name'],
            'dateiname' => $eintrag['name'],
            'mime_type' => $eintrag['mime_type'] ?? null,
            'groesse' => (int) ($eintrag['groesse'] ?? 0),
            'nextcloud_pfad' => $eintrag['pfad'],
            'lokaler_pfad' => null,
            'status' => WpDatei::ABGELEGT,
            'hochgeladen_von' => $userId,
            'geprueft_at' => now(),
        ]);
    }

    private function dateiAnlegen(WpPlan $plan, string $name, string $dateiname, string $lokal, ?string $mime, int $groesse, ?int $userId): WpDatei
    {
        $aktiv = $this->aktiv();

        $datei = WpDatei::create([
            'wp_plan_id' => $plan->id,
            'name' => $name,
            'dateiname' => $dateiname,
            'mime_type' => $mime,
            'groesse' => $groesse,
            'nextcloud_pfad' => $aktiv ? $this->freierPfad($this->dateiOrdner($plan).'/'.$dateiname) : null,
            'lokaler_pfad' => $lokal,
            'status' => $aktiv ? WpDatei::AUSSTEHEND : WpDatei::LOKAL,
            'hochgeladen_von' => $userId,
        ]);

        if ($aktiv) {
            WpDateiHochladen::dispatch($datei->id);
        }

        return $datei;
    }

    /** Vermeidet doppelte Dateinamen im selben Ordner („name (2).pdf“). */
    private function freierPfad(string $pfad): string
    {
        $kandidat = $pfad;
        $info = pathinfo($pfad);
        $n = 2;
        while (WpDatei::where('nextcloud_pfad', $kandidat)->exists()) {
            $kandidat = ($info['dirname'] !== '.' ? $info['dirname'].'/' : '').$info['filename'].' ('.$n.')'
                .(isset($info['extension']) ? '.'.$info['extension'] : '');
            $n++;
        }

        return $kandidat;
    }

    /** Lädt eine Datei aus dem Zwischenspeicher in die Nextcloud. */
    public function dateiHochladen(WpDatei $datei): bool
    {
        if (! $this->aktiv()) {
            $datei->update(['status' => WpDatei::LOKAL]);

            return false;
        }
        if (! $datei->lokaler_pfad || ! Storage::disk('wochenplan')->exists($datei->lokaler_pfad)) {
            $datei->update(['status' => WpDatei::FEHLER, 'fehler' => 'Datei im Zwischenspeicher nicht gefunden.']);

            return false;
        }

        $pfad = $datei->nextcloud_pfad ?: $this->freierPfad($this->dateiOrdner($datei->plan).'/'.$datei->dateiname);
        $ok = $this->nextcloud->hochladen(Storage::disk('wochenplan')->get($datei->lokaler_pfad), $pfad);

        if (! $ok) {
            $datei->update(['status' => WpDatei::FEHLER, 'fehler' => 'Upload in die Nextcloud fehlgeschlagen.', 'nextcloud_pfad' => $pfad]);

            return false;
        }

        Storage::disk('wochenplan')->delete($datei->lokaler_pfad);
        $datei->update([
            'nextcloud_pfad' => $pfad,
            'lokaler_pfad' => null,
            'status' => WpDatei::ABGELEGT,
            'fehler' => null,
            'geprueft_at' => now(),
        ]);

        return true;
    }

    /** Inhalt einer Datei (Zwischenspeicher bevorzugt, sonst Nextcloud). */
    public function dateiInhalt(WpDatei $datei): ?string
    {
        if ($datei->lokaler_pfad && Storage::disk('wochenplan')->exists($datei->lokaler_pfad)) {
            return Storage::disk('wochenplan')->get($datei->lokaler_pfad);
        }

        if ($datei->nextcloud_pfad && $this->aktiv()) {
            $inhalt = $this->nextcloud->herunterladen($datei->nextcloud_pfad);

            return $inhalt === false ? null : $inhalt;
        }

        return null;
    }

    /** Schreibt den Inhalt in eine temporäre Datei (für PDF-Zusammenführung). Aufrufer löscht sie. */
    public function temporaereKopie(WpDatei $datei): ?string
    {
        $inhalt = $this->dateiInhalt($datei);
        if ($inhalt === null) {
            return null;
        }

        $verzeichnis = storage_path('app/temp');
        if (! is_dir($verzeichnis)) {
            mkdir($verzeichnis, 0755, true);
        }
        $endung = pathinfo($datei->dateiname, PATHINFO_EXTENSION);
        $pfad = $verzeichnis.'/'.uniqid('wp_datei_').($endung ? '.'.$endung : '');
        file_put_contents($pfad, $inhalt);

        return $pfad;
    }

    /** Entfernt eine Datei (Nextcloud: nach `_gelöscht/`, Zwischenspeicher: löschen). */
    public function dateiEntfernen(WpDatei $datei): void
    {
        if ($datei->lokaler_pfad) {
            Storage::disk('wochenplan')->delete($datei->lokaler_pfad);
        }

        // Nur eigene Uploads (im Ordner des Plans) wandern nach _gelöscht – verknüpfte
        // Dateien aus anderen Nextcloud-Ordnern bleiben unangetastet.
        $ordner = $datei->plan?->ablage_ordner;
        $eigeneDatei = $ordner && str_starts_with((string) $datei->nextcloud_pfad, $ordner.'/');

        if ($eigeneDatei && $datei->status !== WpDatei::AUSSTEHEND && $this->aktiv()
            && $this->nextcloud->existiert($datei->nextcloud_pfad)) {
            $this->nextcloud->verschieben(
                $datei->nextcloud_pfad,
                self::GELOESCHT_ORDNER.'/'.now()->format('Y-m-d His').' '.basename($datei->nextcloud_pfad)
            );
        }

        $datei->delete();
    }

    /**
     * Nächtliche Prüfung: Sind die abgelegten Arbeitsblätter noch vorhanden?
     *
     * @return array{geprueft: int, fehlt: int, wieder_da: int}
     */
    public function dateienPruefen(): array
    {
        $ergebnis = ['geprueft' => 0, 'fehlt' => 0, 'wieder_da' => 0];
        if (! $this->aktiv()) {
            return $ergebnis;
        }

        WpDatei::whereIn('status', [WpDatei::ABGELEGT, WpDatei::FEHLT])
            ->whereNotNull('nextcloud_pfad')
            ->each(function (WpDatei $datei) use (&$ergebnis) {
                $vorhanden = $this->nextcloud->existiert($datei->nextcloud_pfad);
                $ergebnis['geprueft']++;

                if (! $vorhanden && $datei->status === WpDatei::ABGELEGT) {
                    $ergebnis['fehlt']++;
                    $datei->update(['status' => WpDatei::FEHLT, 'fehler' => 'In der Nextcloud nicht gefunden.', 'geprueft_at' => now()]);
                } elseif ($vorhanden && $datei->status === WpDatei::FEHLT) {
                    $ergebnis['wieder_da']++;
                    $datei->update(['status' => WpDatei::ABGELEGT, 'fehler' => null, 'geprueft_at' => now()]);
                } else {
                    $datei->update(['geprueft_at' => now()]);
                }
            });

        return $ergebnis;
    }

    /** Fehlgeschlagene Uploads bzw. Ablagen eines Plans erneut versuchen. */
    public function erneutVersuchen(WpPlan $plan): void
    {
        WpDatei::where('wp_plan_id', $plan->id)
            ->whereIn('status', [WpDatei::FEHLER, WpDatei::AUSSTEHEND, WpDatei::LOKAL])
            ->whereNotNull('lokaler_pfad')
            ->get()
            ->each(function (WpDatei $datei) {
                $datei->update(['status' => WpDatei::AUSSTEHEND, 'fehler' => null]);
                WpDateiHochladen::dispatch($datei->id);
            });

        $this->anfordern($plan);
    }

    public function webLink(WpPlan $plan): ?string
    {
        if (! $this->aktiv() || ! $plan->ablage_ordner) {
            return null;
        }

        return $this->nextcloud->webLink($plan->ablage_ordner, true);
    }

    public function dateiWebLink(WpDatei $datei): ?string
    {
        if (! $this->aktiv() || ! $datei->nextcloud_pfad || $datei->status !== WpDatei::ABGELEGT) {
            return null;
        }

        return $this->nextcloud->webLink($datei->nextcloud_pfad);
    }

    // ─── intern ───────────────────────────────────────────────────────────────

    private function planDateienVerschieben(string $ordner, string $altDatei, string $neuDatei): void
    {
        foreach (['.pdf', '.docx'] as $endung) {
            $von = $ordner.'/'.$altDatei.$endung;
            if ($this->nextcloud->existiert($von)) {
                $this->nextcloud->verschieben($von, $ordner.'/'.$neuDatei.$endung);
            }
        }
    }

    /** Ersetzt nach einem Ordner-Umzug den Pfad-Präfix am Plan, seinen Kinderplänen und allen Dateien. */
    private function praefixErsetzen(WpPlan $plan, string $alt, string $neu): void
    {
        $planIds = WpPlan::withTrashed()->where('parent_plan_id', $plan->id)->pluck('id')->push($plan->id);

        WpPlan::withTrashed()->where('parent_plan_id', $plan->id)->where('ablage_ordner', $alt)
            ->update(['ablage_ordner' => $neu]);

        WpDatei::whereIn('wp_plan_id', $planIds)->get()->each(function (WpDatei $d) use ($alt, $neu) {
            if ($d->nextcloud_pfad && str_starts_with($d->nextcloud_pfad, $alt.'/')) {
                $d->update(['nextcloud_pfad' => $neu.substr($d->nextcloud_pfad, strlen($alt))]);
            }
        });
    }
}
