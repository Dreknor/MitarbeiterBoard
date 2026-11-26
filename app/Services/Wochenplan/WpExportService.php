<?php

namespace App\Services\Wochenplan;

use App\Models\User;
use App\Models\Wochenplan\WpPlan;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;

/** Sammel-Exporte: alle Kinderpläne eines Klassenplans als ein PDF bzw. als ZIP. */
class WpExportService
{
    public function __construct(
        private WpPdfService $pdf,
        private WpWordService $word,
    ) {
    }

    /** Kinderpläne, die die Person sehen darf, nach Nachname sortiert. */
    public function kinderplaene(WpPlan $plan, User $user)
    {
        return $plan->kinderPlaene()->with('schueler')->get()
            ->filter(fn (WpPlan $k) => $user->can('view', $k))
            ->sortBy(fn (WpPlan $k) => mb_strtolower(($k->schueler?->nachname ?? '').' '.($k->schueler?->vorname ?? '')))
            ->values();
    }

    /** @return string|null Pfad zu einer temporären PDF-Datei */
    public function kinderplaenePdf(WpPlan $plan, User $user): ?string
    {
        $kinder = $this->kinderplaene($plan, $user);
        if ($kinder->isEmpty()) {
            return null;
        }

        $temp = $this->tempVerzeichnis();
        $einzel = [];
        foreach ($kinder as $kind) {
            $pfad = $temp.'/'.uniqid('wp_kind_').'.pdf';
            file_put_contents($pfad, $this->pdf->generate($kind)->output());
            $einzel[] = $pfad;
        }

        $ziel = $temp.'/'.uniqid('wp_sammel_').'.pdf';
        try {
            $fpdi = new Fpdi();
            foreach ($einzel as $pfad) {
                $seiten = $fpdi->setSourceFile($pfad);
                for ($i = 1; $i <= $seiten; $i++) {
                    $vorlage = $fpdi->importPage($i);
                    $groesse = $fpdi->getTemplateSize($vorlage);
                    $fpdi->AddPage($groesse['orientation'], [$groesse['width'], $groesse['height']]);
                    $fpdi->useTemplate($vorlage);
                }
            }
            $fpdi->Output($ziel, 'F');
        } catch (\Throwable $e) {
            Log::warning('Sammel-PDF fehlgeschlagen', ['plan' => $plan->id, 'fehler' => $e->getMessage()]);
            foreach ($einzel as $pfad) {
                @unlink($pfad);
            }

            return null;
        }

        foreach ($einzel as $pfad) {
            @unlink($pfad);
        }

        return $ziel;
    }

    /** @return string|null Pfad zu einer temporären ZIP-Datei */
    public function kinderplaeneZip(WpPlan $plan, User $user): ?string
    {
        $kinder = $this->kinderplaene($plan, $user);
        if ($kinder->isEmpty()) {
            return null;
        }

        $ziel = $this->tempVerzeichnis().'/'.uniqid('wp_zip_').'.zip';
        $zip = new \ZipArchive();
        if ($zip->open($ziel, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $aufraeumen = [];
        $ablage = app(WpAblageService::class);
        foreach ($kinder as $kind) {
            $name = $ablage->bereinigen(($kind->schueler?->nachname ?? 'Kind').', '.($kind->schueler?->vorname ?? $kind->id));
            $zip->addFromString($name.'.pdf', $this->pdf->generate($kind)->output());
            $wordPfad = $this->word->generate($kind);
            $zip->addFile($wordPfad, $name.'.docx');
            $aufraeumen[] = $wordPfad;
        }
        $zip->close();

        foreach ($aufraeumen as $pfad) {
            @unlink($pfad);
        }

        return $ziel;
    }

    public function sammelName(WpPlan $plan): string
    {
        return app(WpAblageService::class)->bereinigen('Kinderpläne '.$plan->name.' '.($plan->klasse?->name ?? '')) ?: 'Kinderplaene';
    }

    private function tempVerzeichnis(): string
    {
        $temp = storage_path('app/temp');
        if (! is_dir($temp)) {
            mkdir($temp, 0755, true);
        }

        return $temp;
    }
}
