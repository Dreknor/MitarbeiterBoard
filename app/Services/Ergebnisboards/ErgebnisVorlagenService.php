<?php

namespace App\Services\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisVorlage;
use App\Models\User;
use App\Support\ErgebnisboardVorlagen;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Vorlagen-Editor (Stufe 3): eigene Vorlagen anlegen, ändern, deaktivieren und aus einem
 * bestehenden Board übernehmen. Eingebaute Vorlagen (ErgebnisboardVorlagen::VORLAGEN) sind fest.
 */
class ErgebnisVorlagenService
{
    /**
     * @param  array{titel: string, beschreibung?: ?string, icon?: ?string, spalten: string, bereiche_frei?: bool, auto_status?: bool, bereiche: array, achsen?: ?array, legende?: ?array, aktiv?: bool}  $daten
     */
    public function speichern(?ErgebnisVorlage $vorlage, array $daten, User $user): ErgebnisVorlage
    {
        $definition = $this->definitionPruefen($daten);

        $vorlage ??= new ErgebnisVorlage([
            'schluessel' => $this->neuerSchluessel($daten['titel']),
            'created_by' => $user->id,
        ]);
        $vorlage->fill([
            'titel' => $daten['titel'],
            'beschreibung' => $daten['beschreibung'] ?? null,
            'definition' => $definition,
            'aktiv' => (bool) ($daten['aktiv'] ?? true),
        ]);
        $vorlage->save();

        return $vorlage;
    }

    /** Raster eines Boards (ohne Karten) als neue Vorlage. */
    public function ausBoard(Ergebnisboard $board, string $titel, ?string $beschreibung, User $user): ErgebnisVorlage
    {
        return $this->speichern(null, [
            'titel' => $titel,
            'beschreibung' => $beschreibung,
            'icon' => ErgebnisboardVorlagen::finden($board->vorlage)['icon'] ?? 'fas fa-th',
            'spalten' => $board->spalten,
            'bereiche_frei' => $board->bereiche_frei,
            'auto_status' => $board->auto_status,
            'bereiche' => $board->bereiche,
            'achsen' => $board->achsen,
            'legende' => $board->legende,
        ], $user);
    }

    public function definitionPruefen(array $daten): array
    {
        $spalten = trim((string) ($daten['spalten'] ?? ''));
        if (! ErgebnisboardVorlagen::spaltenGueltig($spalten)) {
            throw ValidationException::withMessages(['spalten' => 'Spalten bitte als Breiten wie „1fr 2fr 1fr“ angeben (fr, px oder %).']);
        }
        $anzahl = ErgebnisboardVorlagen::spaltenAnzahl($spalten);

        $bereiche = [];
        $schluessel = [];
        $umbenannt = [];
        foreach (array_values((array) ($daten['bereiche'] ?? [])) as $i => $b) {
            $b = ErgebnisboardVorlagen::bereichNormalisieren((array) $b);
            $b['titel'] = Str::limit(trim((string) $b['titel']), 80, '') ?: 'Feld '.($i + 1);
            $original = (string) ($b['schluessel'] ?? '');
            $b['schluessel'] = $this->bereichsSchluessel($original !== '' ? $original : $b['titel'], $schluessel);
            if ($original !== '') {
                $umbenannt[$original] ??= $b['schluessel'];
            }
            $schluessel[] = $b['schluessel'];
            if ($b['eltern'] === null && ($b['spalte'] + $b['spalten'] - 1) > $anzahl) {
                throw ValidationException::withMessages(['bereiche' => '„'.$b['titel'].'“ liegt außerhalb der '.$anzahl.' Spalten.']);
            }
            $bereiche[] = $b;
        }

        // Verweise auf umbenannte Schlüssel (Slug) nachziehen
        foreach ($bereiche as &$b) {
            if ($b['eltern'] !== null && isset($umbenannt[$b['eltern']])) {
                $b['eltern'] = $umbenannt[$b['eltern']];
            }
        }
        unset($b);

        $haupt = array_column(array_filter($bereiche, fn ($b) => $b['eltern'] === null), 'schluessel');
        if (empty($haupt)) {
            throw ValidationException::withMessages(['bereiche' => 'Mindestens ein Feld ist nötig.']);
        }
        foreach ($bereiche as $b) {
            if ($b['eltern'] !== null && ! in_array($b['eltern'], $haupt, true)) {
                throw ValidationException::withMessages(['bereiche' => 'Unterbereich „'.$b['titel'].'“ verweist auf kein Hauptfeld.']);
            }
        }

        return [
            'icon' => preg_match('/^(fas|far|fab) fa-[a-z0-9-]+$/', (string) ($daten['icon'] ?? '')) ? $daten['icon'] : 'fas fa-th',
            'spalten' => $spalten,
            'bereiche_frei' => (bool) ($daten['bereiche_frei'] ?? false),
            'auto_status' => (bool) ($daten['auto_status'] ?? false),
            'achsen' => $this->achsenPruefen($daten['achsen'] ?? null),
            'legende' => $this->legendePruefen($daten['legende'] ?? null),
            'bereiche' => $bereiche,
        ];
    }

    private function achsenPruefen(?array $achsen): ?array
    {
        if (! $achsen) {
            return null;
        }
        $erlaubt = ['y' => ['titel', 'zusatz', 'oben', 'unten'], 'x' => ['titel', 'ziel', 'links', 'rechts']];
        $ergebnis = [];
        foreach ($erlaubt as $achse => $felder) {
            foreach ($felder as $f) {
                $wert = trim((string) ($achsen[$achse][$f] ?? ''));
                if ($wert !== '') {
                    $ergebnis[$achse][$f] = Str::limit($wert, 60, '');
                }
            }
        }

        return $ergebnis ?: null;
    }

    private function legendePruefen(?array $legende): ?array
    {
        $eintraege = collect($legende['eintraege'] ?? [])
            ->map(fn ($e) => [
                'farbe' => array_key_exists($e['farbe'] ?? '', ErgebnisboardVorlagen::FARBEN) ? $e['farbe'] : 'grau',
                'text' => Str::limit(trim((string) ($e['text'] ?? '')), 120, ''),
            ])
            ->filter(fn ($e) => $e['text'] !== '')->values()->all();

        return $eintraege ? ['titel' => Str::limit(trim((string) ($legende['titel'] ?? 'Legende')), 80, ''), 'eintraege' => $eintraege] : null;
    }

    private function bereichsSchluessel(string $basis, array $belegt): string
    {
        $basis = Str::limit(Str::slug($basis, '_'), 40, '') ?: 'feld';
        $s = $basis;
        $n = 2;
        while (in_array($s, $belegt, true)) {
            $s = $basis.'_'.$n++;
        }

        return $s;
    }

    private function neuerSchluessel(string $titel): string
    {
        $basis = 'eigen_'.(Str::limit(Str::slug($titel, '_'), 40, '') ?: 'vorlage');
        $s = $basis;
        $n = 2;
        while (isset(ErgebnisboardVorlagen::VORLAGEN[$s]) || ErgebnisVorlage::query()->where('schluessel', $s)->exists()) {
            $s = $basis.'_'.$n++;
        }

        return $s;
    }
}
