<?php

namespace App\Services;

use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryColumnValue;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Spaltenwerte des Pädagogischen Tagebuchs speichern und die an einem Tag gültigen Werte ermitteln.
 *
 * Regel „bis Wochenende“ (Spaltengruppen-Einstellung, nur Ja/Nein- und Ampel-Spalten):
 * - Gespeichert wird nur der Wert am Tag der Änderung (mit bis_wochenende = true).
 * - Er gilt ab diesem Tag bis Freitag derselben Woche – bis zur nächsten ausdrücklichen Änderung.
 *   Auch Zurücknehmen (leerer Wert) ist eine Änderung ab ihrem Tag.
 * - Nicht rückwirkend; spätere ausdrückliche Einträge werden nie überschrieben; jeder Montag beginnt neu.
 * - Werte ohne bis_wochenende (z. B. alle vor Einführung) gelten wie bisher nur für ihren Tag.
 *
 * Gemeinsam genutzt von Web (Wochenansicht, Schülerakte, Excel-Export) und API v1.
 */
class PaedDiarySpaltenwertService
{
    /**
     * Wert speichern (ein Datensatz je Spalte/Schüler/Tag). Leerer Wert = nicht gesetzt/zurückgenommen.
     */
    public function speichern(PaedDiaryColumn $column, int $schuelerId, Carbon $datum, ?string $value): PaedDiaryColumnValue
    {
        $value = ($value === null || $value === '') ? null : $value;

        // whereDate statt Gleichheit: `datum` wird je nach Datenbank mit Uhrzeit gespeichert
        $wert = PaedDiaryColumnValue::where('paed_diary_column_id', $column->id)
            ->where('schueler_id', $schuelerId)
            ->whereDate('datum', $datum->toDateString())
            ->first();

        $daten = ['value' => $value, 'bis_wochenende' => $column->wirktBisWochenende()];

        if ($wert) {
            $wert->update($daten);

            return $wert;
        }

        return PaedDiaryColumnValue::create($daten + [
            'paed_diary_column_id' => $column->id,
            'schueler_id' => $schuelerId,
            'datum' => $datum->toDateString(),
        ]);
    }

    /**
     * Gespeicherte Werte im Zeitraum laden – ab Montag der Woche von $von, damit Werte vom
     * Wochenanfang in den Zeitraum hinein weiterwirken können.
     *
     * @param iterable<int> $schuelerIds
     */
    public function laden(iterable $schuelerIds, Carbon $von, Carbon $bis, ?iterable $spaltenIds = null): Collection
    {
        return PaedDiaryColumnValue::whereIn('schueler_id', collect($schuelerIds)->all())
            ->when($spaltenIds !== null, fn ($q) => $q->whereIn('paed_diary_column_id', collect($spaltenIds)->all()))
            ->whereDate('datum', '>=', $von->copy()->startOfWeek()->toDateString())
            ->whereDate('datum', '<=', $bis->toDateString())
            ->get();
    }

    /**
     * Die im Zeitraum gültigen Werte: gespeicherte Werte im Zeitraum plus (nicht gespeicherte)
     * übernommene Werte. Jeder Wert trägt das Attribut `uebernommen_von` (Datum Y-m-d des Tags,
     * an dem er gesetzt wurde, oder null bei einem am Tag selbst gespeicherten Wert).
     *
     * @param Collection<PaedDiaryColumnValue> $werte gespeicherte Werte, mindestens ab Montag der Woche von $von
     * @return Collection<PaedDiaryColumnValue> sortiert nach Datum
     */
    public function wirksameWerte(Collection $werte, Carbon $von, Carbon $bis): Collection
    {
        $von = $von->toDateString();
        $bis = $bis->toDateString();
        $ergebnis = collect();

        $gruppen = $werte->groupBy(fn (PaedDiaryColumnValue $w) => $w->paed_diary_column_id . '|' . $w->schueler_id . '|'
            . Carbon::parse($w->datum)->startOfWeek()->toDateString());

        foreach ($gruppen as $gruppe) {
            /** @var Collection<string, PaedDiaryColumnValue> $proTag */
            $proTag = $gruppe->keyBy(fn (PaedDiaryColumnValue $w) => Carbon::parse($w->datum)->toDateString())->sortKeys();
            $erster = $gruppe->first();
            $montag = Carbon::parse($erster->datum)->startOfWeek();

            // Gespeicherte Werte im Zeitraum unverändert übernehmen
            foreach ($proTag as $tag => $wert) {
                if ($tag >= $von && $tag <= $bis) {
                    $wert->setAttribute('uebernommen_von', null);
                    $ergebnis->push($wert);
                }
            }

            // Lücken Mo–Fr mit dem zuletzt gesetzten Wert der Woche füllen
            $letzter = null; // zuletzt maßgeblicher gespeicherter Wert vor dem jeweiligen Tag
            for ($i = 0; $i < 5; $i++) {
                $tag = $montag->copy()->addDays($i)->toDateString();
                $eigener = $proTag->get($tag);

                if ($eigener && $this->istAenderung($eigener)) {
                    $letzter = $eigener;
                    continue;
                }
                if ($tag < $von || $tag > $bis || !$letzter || !$letzter->bis_wochenende || $this->istLeer($letzter->value)) {
                    continue;
                }

                // Ein leerer Altwert am Tag selbst (ohne bis_wochenende) bedeutet „nichts gesetzt“
                // und wird durch den übernommenen Wert ersetzt.
                if ($eigener) {
                    $ergebnis = $ergebnis->reject(fn ($w) => $w === $eigener);
                }

                $uebernommen = new PaedDiaryColumnValue([
                    'paed_diary_column_id' => $letzter->paed_diary_column_id,
                    'schueler_id' => $letzter->schueler_id,
                    'datum' => $tag,
                    'value' => $letzter->value,
                    'bis_wochenende' => true,
                ]);
                $uebernommen->setAttribute('uebernommen_von', Carbon::parse($letzter->datum)->toDateString());
                $ergebnis->push($uebernommen);
            }
        }

        return $ergebnis
            ->sortBy(fn (PaedDiaryColumnValue $w) => Carbon::parse($w->datum)->toDateString() . '|' . str_pad((string) $w->paed_diary_column_id, 10, '0', STR_PAD_LEFT))
            ->values();
    }

    /**
     * Gültige Werte einer Spalte für einen Schüler in der Woche von $datum (Mo–Fr),
     * z. B. als Antwort nach dem Speichern.
     *
     * @return list<array{date: string, value: ?string, carried_from: ?string}>
     */
    public function woche(PaedDiaryColumn $column, int $schuelerId, Carbon $datum): array
    {
        $montag = $datum->copy()->startOfWeek();
        $freitag = $montag->copy()->addDays(4);
        $werte = $this->wirksameWerte($this->laden([$schuelerId], $montag, $freitag, [$column->id]), $montag, $freitag)
            ->keyBy(fn ($w) => Carbon::parse($w->datum)->toDateString());

        $tage = [];
        for ($i = 0; $i < 5; $i++) {
            $tag = $montag->copy()->addDays($i)->toDateString();
            $wert = $werte->get($tag);
            $tage[] = [
                'date' => $tag,
                'value' => $wert && !$this->istLeer($wert->value) ? $wert->value : null,
                'carried_from' => $wert?->uebernommen_von,
            ];
        }

        return $tage;
    }

    /**
     * Unterbricht ein gespeicherter Wert die Übernahme? Ja, wenn er einen Inhalt hat oder als
     * Änderung „bis Wochenende“ gespeichert wurde (auch leer = zurückgenommen). Leere Altwerte
     * ohne diese Kennzeichnung bedeuten nur „nichts gesetzt“.
     */
    private function istAenderung(PaedDiaryColumnValue $wert): bool
    {
        return $wert->bis_wochenende || !$this->istLeer($wert->value);
    }

    private function istLeer(?string $value): bool
    {
        return $value === null || $value === '';
    }
}
