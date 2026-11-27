<?php

namespace App\Services\Arbeitsblaetter;

use App\Models\Arbeitsblaetter\AbFassung;
use App\Models\Arbeitsblaetter\AbProfilVorlage;
use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Models\Schueler;
use App\Models\User;
use App\Services\Wochenplan\WpAblageService;
use App\Support\AbProfil;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

/**
 * Ausgabe der Angepassten Arbeitsblätter.
 *
 * - Eine Fassung je Blatt und Ziel (Kind bzw. Profil-Vorlage); sie wird wiederverwendet,
 *   solange `profil_hash` und `struktur_version` gleich sind, sonst neu gesetzt.
 * - Sammel-PDF: alle Fassungen hintereinander (FPDI, Seitengröße bleibt erhalten).
 * - Nextcloud-Ablage nach `ab_ordner_vorlage`/`ab_datei_vorlage`
 *   (Platzhalter {schuljahr} {klasse} {vorname} {nachname} {titel} {datum}).
 */
class AbAusgabeService
{
    public const DISK = 'arbeitsblaetter';

    public const PLATZHALTER = ['schuljahr', 'klasse', 'vorname', 'nachname', 'titel', 'datum'];

    public const STANDARD_VORLAGEN = [
        'ordner' => 'Angepasste Arbeitsblätter/{schuljahr}/{nachname} {vorname}',
        'datei' => '{titel}',
    ];

    public function __construct(
        private AbSatzService $satz,
        private SehprofilService $profile,
        private AbNextcloudSpeicher $nextcloud,
    ) {
    }

    // ─── Fassungen ────────────────────────────────────────────────────────────

    /**
     * Fassung für ein Kind (mit aktivem Profil) oder eine Profil-Vorlage – wiederverwendet
     * oder neu gesetzt. Liefert null, wenn das Kind kein aktives Profil hat.
     */
    public function fassung(AngepasstesBlatt $blatt, ?Schueler $kind, ?AbProfilVorlage $vorlage = null, ?User $user = null): ?AbFassung
    {
        if ($kind) {
            $werte = $this->profile->wirksameWerte($kind);
            if ($werte === null) {
                return null;
            }
        } elseif ($vorlage) {
            $werte = $vorlage->wirksameWerte();
        } else {
            return null;
        }

        $hash = AbProfil::hash($werte);
        $version = (int) $blatt->version;

        $fassung = AbFassung::query()
            ->where('blatt_id', $blatt->id)
            ->when($kind, fn ($q) => $q->where('schueler_id', $kind->id), fn ($q) => $q->whereNull('schueler_id')->where('vorlage_id', $vorlage->id))
            ->first();

        $docxGewuenscht = (bool) settings('ab_docx_ausgabe');
        $wiederverwendbar = $fassung
            && $fassung->status === AbFassung::AKTUELL
            && $fassung->profil_hash === $hash
            && (int) $fassung->struktur_version === $version
            && $fassung->pdf_pfad && Storage::disk(self::DISK)->exists($fassung->pdf_pfad)
            && (! $docxGewuenscht || ($fassung->docx_pfad && Storage::disk(self::DISK)->exists($fassung->docx_pfad)));

        if ($wiederverwendbar) {
            return $fassung;
        }

        $fassung ??= new AbFassung(['blatt_id' => $blatt->id, 'schueler_id' => $kind?->id, 'vorlage_id' => $kind ? null : $vorlage->id]);
        $fusszeile = $kind ? $kind->vorname.' '.$kind->nachname : null;

        $pdf = $this->satz->pdf($blatt, $werte, null, $fusszeile);
        $docx = $docxGewuenscht ? $this->satz->docx($blatt, $werte, null, $fusszeile) : null;

        $fassung->fill([
            'profil_werte' => $werte,
            'profil_hash' => $hash,
            'struktur_version' => $version,
            'status' => AbFassung::AKTUELL,
            'erzeugt_von' => $user?->id ?? $fassung->erzeugt_von,
        ]);
        $fassung->save();

        $basis = 'fassungen/'.$blatt->id.'/'.$fassung->id;
        Storage::disk(self::DISK)->put($basis.'.pdf', $pdf);
        $fassung->pdf_pfad = $basis.'.pdf';
        if ($docx !== null) {
            Storage::disk(self::DISK)->put($basis.'.docx', $docx);
            $fassung->docx_pfad = $basis.'.docx';
        } elseif ($fassung->docx_pfad) {
            Storage::disk(self::DISK)->delete($fassung->docx_pfad);
            $fassung->docx_pfad = null;
        }
        $fassung->save();
        $fassung->neuGesetzt = true;

        return $fassung;
    }

    /**
     * Fassungen für ausgewählte Kinder und/oder Profil-Vorlagen.
     *
     * @return Collection<int, AbFassung>
     */
    public function erzeugen(AngepasstesBlatt $blatt, array $schuelerIds, array $vorlageIds, ?User $user): Collection
    {
        $fassungen = collect();

        Schueler::with('klasse')->whereIn('id', $schuelerIds)->orderBy('nachname')->orderBy('vorname')->get()
            ->each(function (Schueler $kind) use ($blatt, $user, $fassungen) {
                $fassung = $this->fassung($blatt, $kind, null, $user);
                if ($fassung) {
                    $fassungen->push($fassung);
                }
            });

        AbProfilVorlage::whereIn('id', $vorlageIds)->orderBy('sortierung')->get()
            ->each(fn (AbProfilVorlage $v) => $fassungen->push($this->fassung($blatt, null, $v, $user)));

        return $fassungen->filter()->values();
    }

    /** Alle Fassungen eines Blatts werden veraltet (Struktur nach Freigabe geändert). */
    public function blattVeraltet(AngepasstesBlatt $blatt): int
    {
        return AbFassung::where('blatt_id', $blatt->id)->where('status', AbFassung::AKTUELL)
            ->update(['status' => AbFassung::VERALTET, 'updated_at' => now()]);
    }

    public function pdfInhalt(AbFassung $fassung): ?string
    {
        return $fassung->pdf_pfad && Storage::disk(self::DISK)->exists($fassung->pdf_pfad)
            ? Storage::disk(self::DISK)->get($fassung->pdf_pfad) : null;
    }

    public function docxInhalt(AbFassung $fassung): ?string
    {
        return $fassung->docx_pfad && Storage::disk(self::DISK)->exists($fassung->docx_pfad)
            ? Storage::disk(self::DISK)->get($fassung->docx_pfad) : null;
    }

    /** Entfernt die Dateien einer Fassung (beim Löschen eines Blatts). */
    public function dateienEntfernen(AbFassung $fassung): void
    {
        foreach ([$fassung->pdf_pfad, $fassung->docx_pfad] as $pfad) {
            if ($pfad) {
                Storage::disk(self::DISK)->delete($pfad);
            }
        }
    }

    /** Sammel-PDF aller Fassungen (Seiten zusammenführen, Seitengröße je Fassung). */
    public function sammelPdf(Collection $fassungen): string
    {
        $pdf = new Fpdi();
        $temp = [];
        try {
            foreach ($fassungen as $fassung) {
                $inhalt = $this->pdfInhalt($fassung);
                if ($inhalt === null) {
                    continue;
                }
                $pfad = storage_path('app/temp/'.uniqid('ab_sammel_', true).'.pdf');
                if (! is_dir(dirname($pfad))) {
                    @mkdir(dirname($pfad), 0755, true);
                }
                file_put_contents($pfad, $inhalt);
                $temp[] = $pfad;

                $seiten = $pdf->setSourceFile($pfad);
                for ($i = 1; $i <= $seiten; $i++) {
                    $vorlage = $pdf->importPage($i);
                    $groesse = $pdf->getTemplateSize($vorlage);
                    $pdf->AddPage($groesse['orientation'], [$groesse['width'], $groesse['height']]);
                    $pdf->useTemplate($vorlage);
                }
            }

            return $pdf->Output('S');
        } finally {
            foreach ($temp as $pfad) {
                @unlink($pfad);
            }
        }
    }

    // ─── Nextcloud ────────────────────────────────────────────────────────────

    public function nextcloudAktiv(): bool
    {
        return $this->nextcloud->aktiv();
    }

    /** @return array{ordner: string, datei: string} */
    public function vorlagen(): array
    {
        return [
            'ordner' => (string) (settings('ab_ordner_vorlage') ?: self::STANDARD_VORLAGEN['ordner']),
            'datei' => (string) (settings('ab_datei_vorlage') ?: self::STANDARD_VORLAGEN['datei']),
        ];
    }

    /** Prüft eine Ordner-/Dateivorlage wie {@see WpAblageService::vorlageFehler()}. */
    public function vorlageFehler(string $vorlage): ?string
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
        if (str_starts_with($vorlage, '/')) {
            return 'Die Vorlage muss relativ sein (ohne führendes /).';
        }
        foreach (explode('/', $vorlage) as $teil) {
            if (in_array(trim($teil), ['.', '..'], true)) {
                return 'Die Vorlage darf keine Ordner „.“ oder „..“ enthalten.';
            }
        }
        preg_match_all('/\{([^}]*)\}/', $vorlage, $treffer);
        foreach ($treffer[1] as $name) {
            if (! in_array($name, self::PLATZHALTER, true)) {
                return 'Unbekannter Platzhalter {'.$name.'}. Erlaubt: {'.implode('} {', self::PLATZHALTER).'}.';
            }
        }
        if (substr_count($vorlage, '{') !== substr_count($vorlage, '}')) {
            return 'Geschweifte Klammern sind nicht vollständig.';
        }

        return null;
    }

    public function platzhalterWerte(AngepasstesBlatt $blatt, ?Schueler $kind, ?AbProfilVorlage $vorlage, ?\DateTimeInterface $datum = null): array
    {
        $datum = $datum ? \Carbon\Carbon::instance($datum) : now();
        $start = $datum->month >= 8 ? $datum->year : $datum->year - 1;

        return [
            'schuljahr' => $start.'-'.substr((string) ($start + 1), -2),
            'klasse' => $kind?->klasse?->name ?: 'Ohne Klasse',
            'vorname' => $kind?->vorname ?? '',
            'nachname' => $kind?->nachname ?? ('Vorlage '.($vorlage?->name ?? '')),
            'titel' => $blatt->titel ?: 'Arbeitsblatt',
            'datum' => $datum->format('Y-m-d'),
        ];
    }

    /** Zielpfad ohne Endung, z. B. „Angepasste Arbeitsblätter/2026-27/Muster Mia/Brüche“. */
    public function zielPfad(AngepasstesBlatt $blatt, ?Schueler $kind, ?AbProfilVorlage $vorlage): string
    {
        $ablage = app(WpAblageService::class);
        $werte = $this->platzhalterWerte($blatt, $kind, $vorlage);
        $vorlagen = $this->vorlagen();
        $einsetzen = function (string $muster) use ($werte, $ablage): string {
            $ergebnis = preg_replace_callback('/\{([a-z]+)\}/', fn ($m) => (string) ($werte[$m[1]] ?? ''), $muster);
            $teile = array_values(array_filter(array_map(fn ($t) => $ablage->bereinigen($t), explode('/', (string) $ergebnis)), fn ($t) => $t !== ''));

            return implode('/', $teile);
        };

        $ordner = $einsetzen($vorlagen['ordner']) ?: 'Angepasste Arbeitsblätter';
        $datei = str_replace('/', '-', $einsetzen($vorlagen['datei'])) ?: 'Arbeitsblatt';

        return $ordner.'/'.$datei;
    }

    /** Legt PDF (und ggf. DOCX) einer Fassung in der Nextcloud ab. */
    public function ablegen(AbFassung $fassung): bool
    {
        if (! $this->nextcloud->aktiv()) {
            return false;
        }
        $fassung->loadMissing(['blatt', 'schueler.klasse', 'vorlage']);
        $basis = $this->zielPfad($fassung->blatt, $fassung->schueler, $fassung->vorlage);

        $pdf = $this->pdfInhalt($fassung);
        if ($pdf === null || ! $this->nextcloud->hochladen($pdf, $basis.'.pdf')) {
            Log::warning('Angepasste Arbeitsblätter: Ablage fehlgeschlagen', ['fassung' => $fassung->id, 'pfad' => $basis]);

            return false;
        }
        $docx = $this->docxInhalt($fassung);
        if ($docx !== null) {
            $this->nextcloud->hochladen($docx, $basis.'.docx');
        }
        $fassung->update(['nextcloud_pfad' => $basis.'.pdf']);

        return true;
    }

    public function webLink(AbFassung $fassung): ?string
    {
        return $fassung->nextcloud_pfad && $this->nextcloud->aktiv() ? $this->nextcloud->webLink($fassung->nextcloud_pfad) : null;
    }
}
