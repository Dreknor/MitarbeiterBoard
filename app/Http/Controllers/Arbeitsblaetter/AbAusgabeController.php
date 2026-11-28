<?php

namespace App\Http\Controllers\Arbeitsblaetter;

use App\Http\Controllers\Controller;
use App\Jobs\Arbeitsblaetter\AbFassungenErzeugen;
use App\Models\Arbeitsblaetter\AbFassung;
use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Services\Arbeitsblaetter\AbAusgabeService;
use App\Services\Arbeitsblaetter\AbWochenplanService;
use App\Services\Arbeitsblaetter\SehprofilService;
use App\Support\AbProfil;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ausgabe: Kinder (mit Profil) und/oder Profil-Vorlagen wählen → PDF/DOCX einzeln oder als
 * Sammel-PDF herunterladen oder in der Nextcloud ablegen (Job).
 */
class AbAusgabeController extends Controller
{
    public function __construct(
        private AbAusgabeService $ausgabe,
        private SehprofilService $profile,
    ) {
    }

    public function show(Request $request, AngepasstesBlatt $blatt)
    {
        $this->authorize('ausgeben', $blatt);

        $kinder = $this->profile->kinderMitProfil();
        $fassungen = AbFassung::with(['schueler', 'vorlage'])->where('blatt_id', $blatt->id)->get();

        return view('arbeitsblaetter.ausgabe', [
            'blatt' => $blatt,
            'kinder' => $kinder->groupBy(fn ($k) => $k->klasse?->name ?? 'Ohne Klasse')->sortKeys(),
            'profilKurz' => $kinder->mapWithKeys(fn ($k) => [$k->id => AbProfil::kurz($this->profile->wirksameWerte($k) ?? [])]),
            'meineKlassen' => $request->user()->paedKlassenIds()->all(),
            'vorlagen' => $this->profile->vorlagen(),
            'fassungen' => $fassungen,
            'wochenplanFassungen' => app(AbWochenplanService::class)->wochenplanFassungen($blatt),
            'nextcloud' => $this->ausgabe->nextcloudAktiv(),
            'docx' => (bool) settings('ab_docx_ausgabe'),
            'zielBeispiel' => $this->ausgabe->zielPfad($blatt, $kinder->first(), null),
        ]);
    }

    public function erzeugen(Request $request, AngepasstesBlatt $blatt)
    {
        $this->authorize('ausgeben', $blatt);
        $daten = $request->validate([
            'kinder' => 'nullable|array',
            'kinder.*' => 'integer',
            'vorlagen' => 'nullable|array',
            'vorlagen.*' => 'integer',
            'aktion' => 'required|in:einzeln,sammel,nextcloud',
            'format' => 'nullable|in:pdf,docx',
        ]);

        // Nur Kinder mit aktivem Profil
        $erlaubt = $this->profile->kinderMitProfil()->pluck('id')->all();
        $kinder = array_values(array_intersect(array_map('intval', $daten['kinder'] ?? []), $erlaubt));
        $vorlagen = array_map('intval', $daten['vorlagen'] ?? []);
        if (! $kinder && ! $vorlagen) {
            throw ValidationException::withMessages(['kinder' => 'Bitte mindestens ein Kind oder eine Profil-Vorlage auswählen.']);
        }

        if ($daten['aktion'] === 'nextcloud') {
            abort_unless($this->ausgabe->nextcloudAktiv(), 422, 'Die Nextcloud ist nicht eingerichtet.');
            AbFassungenErzeugen::dispatch($blatt->id, $kinder, $vorlagen, $request->user()->id, true);

            return redirect()->route('arbeitsblaetter.ausgabe', $blatt)->with([
                'type' => 'success',
                'Meldung' => 'Die Fassungen werden erzeugt und in der Nextcloud abgelegt.',
            ]);
        }

        $fassungen = $this->ausgabe->erzeugen($blatt, $kinder, $vorlagen, $request->user());
        abort_if($fassungen->isEmpty(), 422, 'Es konnte keine Fassung erzeugt werden.');

        $titel = Str::slug($blatt->titel, '-', 'de') ?: 'arbeitsblatt';

        if ($daten['aktion'] === 'sammel' || $fassungen->count() > 1) {
            return $this->download($this->ausgabe->sammelPdf($fassungen), $titel.'-angepasst.pdf', 'application/pdf');
        }

        return $this->dateiAntwort($fassungen->first(), $daten['format'] ?? 'pdf');
    }

    public function datei(Request $request, AngepasstesBlatt $blatt, AbFassung $fassung, string $art)
    {
        $this->authorize('view', $blatt);
        abort_unless((int) $fassung->blatt_id === (int) $blatt->id && in_array($art, ['pdf', 'docx'], true), 404);

        return $this->dateiAntwort($fassung, $art, $art === 'pdf' && $request->boolean('inline'));
    }

    private function dateiAntwort(AbFassung $fassung, string $art, bool $inline = false)
    {
        $fassung->loadMissing(['blatt', 'schueler', 'vorlage']);
        $inhalt = $art === 'docx' ? $this->ausgabe->docxInhalt($fassung) : $this->ausgabe->pdfInhalt($fassung);
        abort_if($inhalt === null, 404, $art === 'docx' ? 'Für diese Fassung gibt es keine Word-Datei (in den Einstellungen einschalten).' : 'Die Datei ist nicht mehr vorhanden.');

        $name = trim(($fassung->blatt->titel ?: 'Arbeitsblatt').' – '.$fassung->empfaenger).'.'.$art;
        $mime = $art === 'docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/pdf';

        return $this->download($inhalt, $name, $mime, $inline);
    }

    /** `$inline` = im Browser anzeigen (Vorschau im Schnellmodus) statt herunterladen. */
    private function download(string $inhalt, string $name, string $mime, bool $inline = false)
    {
        $name = str_replace(['"', '/', '\\'], '', $name);

        return response($inhalt, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.Str::ascii($name).'"; filename*=UTF-8\'\''.rawurlencode($name),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
