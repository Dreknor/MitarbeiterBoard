<?php

namespace App\Services;

use App\Models\Klasse;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryColumnValue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bearbeiten vorhandener Zusatzspalten im Pädagogischen Tagebuch.
 *
 * Eine Spalte ist eine Zeile pro Klasse; alle Kopien derselben Spalte teilen die spalten_uid.
 * Regeln:
 * - Name, Gruppe und Typ gelten immer für alle Kopien. Gibt es Kopien in Klassen, die die Person
 *   nicht bearbeiten darf, braucht sie dafür „manage global paed diary categories“.
 * - Der Typ lässt sich nur ändern, solange keine Kopie Werte hat.
 * - Klassen: Normale Nutzer:innen ändern nur die Zuordnung zu ihren eigenen Tagebuch-Klassen,
 *   mit dem Sonderrecht sind alle Klassen wählbar.
 *   Abwählen deaktiviert die Kopie ab heute (Werte bleiben). Auswählen reaktiviert eine
 *   deaktivierte Kopie, übernimmt eine vorhandene gleiche Spalte (Name, Typ, Gruppe) samt Werten
 *   oder legt eine neue Kopie am Ende der Sortierung an.
 * - Kollisionen (andere aktive Spalte mit gleichem Name und gleicher Gruppe in einer Klasse)
 *   werden abgelehnt, nie automatisch zusammengeführt.
 */
class PaedDiarySpaltenService
{
    public const TYPEN = ['boolean', 'ampel', 'text', 'number'];

    public function darfGlobal(User $user): bool
    {
        return $user->can('manage global paed diary categories');
    }

    /** Klassen, deren Spaltenzuordnung die Person ändern darf (nach Name sortiert). */
    public function bearbeitbareKlassen(User $user): Collection
    {
        $query = $this->darfGlobal($user) ? Klasse::query() : $user->paed_klassen();

        return $query->orderBy('name')->get(['klassen.id', 'klassen.name']);
    }

    /** Darf die Person diese Spalte überhaupt bearbeiten (eigene Klasse oder Sonderrecht)? */
    public function darfBearbeiten(User $user, PaedDiaryColumn $spalte): bool
    {
        return $this->darfGlobal($user)
            || $user->paed_klassen()->where('klassen.id', $spalte->klasse_id)->exists();
    }

    /** Alle Kopien der Spalte (alle Klassen, auch deaktivierte). */
    public function kopien(PaedDiaryColumn $spalte): Collection
    {
        if (!$spalte->spalten_uid) {
            return collect([$spalte]);
        }

        return $spalte->kopien()->orderBy('id')->get();
    }

    /**
     * Daten für den Bearbeiten-Dialog.
     */
    public function uebersicht(PaedDiaryColumn $spalte, User $user): array
    {
        $kopien = $this->kopien($spalte);
        $klassen = $this->bearbeitbareKlassen($user);
        $bearbeitbar = $klassen->pluck('id')->map(fn ($id) => (int) $id);
        $fremdeKlassen = $kopien->pluck('klasse_id')->map(fn ($id) => (int) $id)->unique()->diff($bearbeitbar);
        $darfGlobal = $this->darfGlobal($user);

        return [
            'id' => $spalte->id,
            'name' => $spalte->name,
            'type' => $spalte->type,
            'category' => $spalte->category,
            'typ_aenderbar' => !$this->hatWerte($kopien),
            'weitere_klassen' => $fremdeKlassen->count(),
            // Name, Gruppe und Typ wirken auf alle Kopien – mit Kopien in fremden Klassen nur mit Sonderrecht
            'eigenschaften_gesperrt' => $fremdeKlassen->isNotEmpty() && !$darfGlobal,
            'klassen' => $klassen->map(function ($klasse) use ($kopien) {
                $kopie = $this->kopieInKlasse($kopien, (int) $klasse->id);

                return [
                    'id' => (int) $klasse->id,
                    'name' => $klasse->name,
                    'status' => !$kopie ? 'keine' : ($kopie->deactivated_from ? 'deaktiviert' : 'aktiv'),
                    'deaktiviert_seit' => $kopie?->deactivated_from?->toDateString(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Spalte bearbeiten.
     *
     * @param array{name:string, category:?string, type:string, klasse_ids:?array} $daten
     *        klasse_ids = null lässt die Klassenzuordnung unverändert.
     * @return array{neu:int, reaktiviert:int, uebernommen:int, deaktiviert:int}
     *
     * @throws ValidationException
     */
    public function aktualisieren(PaedDiaryColumn $spalte, User $user, array $daten): array
    {
        return DB::transaction(function () use ($spalte, $user, $daten) {
            $spalte->refresh();
            $kopien = $this->kopien($spalte);
            $uid = $spalte->spalten_uid ?: (string) Str::uuid();

            $name = trim((string) $daten['name']);
            $gruppe = trim((string) ($daten['category'] ?? '')) ?: null;
            $typ = (string) ($daten['type'] ?? $spalte->type);

            $klassen = $this->bearbeitbareKlassen($user)->keyBy(fn ($k) => (int) $k->id);
            $fremdeKlassen = $kopien->pluck('klasse_id')->map(fn ($id) => (int) $id)->unique()
                ->diff($klassen->keys());

            $nameGeaendert = $name !== $spalte->name;
            $gruppeGeaendert = $gruppe !== ($spalte->category ?: null);
            $typGeaendert = $typ !== $spalte->type;

            if (($nameGeaendert || $gruppeGeaendert || $typGeaendert) && $fremdeKlassen->isNotEmpty() && !$this->darfGlobal($user)) {
                throw ValidationException::withMessages([
                    'name' => 'Die Spalte gibt es auch in ' . $this->anzahlKlassen($fremdeKlassen->count())
                        . ', die du nicht bearbeiten darfst. Name, Gruppe und Art kann dann nur ändern, wer globale Spaltengruppen verwalten darf.',
                ]);
            }

            if ($typGeaendert && $this->hatWerte($kopien)) {
                throw ValidationException::withMessages([
                    'type' => 'Die Art lässt sich nicht mehr ändern, weil in der Spalte schon Werte eingetragen sind.',
                ]);
            }

            // ── Zielzustand je bearbeitbarer Klasse bestimmen ──────────────────
            $gewaehlt = $daten['klasse_ids'] === null
                ? null
                : collect($daten['klasse_ids'])->map(fn ($id) => (int) $id)->unique()->intersect($klassen->keys());

            $reaktivieren = [];  // Kopien, die wieder aktiv werden
            $deaktivieren = [];  // Kopien, die ab heute deaktiviert werden
            $uebernehmen = [];   // vorhandene gleiche Spalten anderer Verknüpfung (werden mit verknüpft)
            $anlegen = [];       // Klassen, in denen eine neue Kopie entsteht
            $fehler = [];

            if ($gewaehlt !== null) {
                foreach ($klassen as $klasseId => $klasse) {
                    $kopie = $this->kopieInKlasse($kopien, $klasseId);
                    $aktiv = $kopie && !$kopie->deactivated_from;

                    if (!$gewaehlt->contains($klasseId)) {
                        if ($aktiv) {
                            $deaktivieren[] = $kopie;
                        }
                        continue;
                    }
                    if ($aktiv) {
                        continue;
                    }
                    if ($kopie) {
                        $reaktivieren[] = $kopie;
                        continue;
                    }

                    // Gibt es in der Klasse schon eine passende Spalte (gleicher Name und gleiche Gruppe)?
                    $vorhanden = PaedDiaryColumn::where('klasse_id', $klasseId)
                        ->whereNull('deactivated_from')
                        ->gleichBenannt($name, $gruppe)
                        ->first();
                    if ($vorhanden && $vorhanden->type === $typ) {
                        $uebernehmen[] = $vorhanden;
                    } elseif ($vorhanden) {
                        $fehler[] = $klasse->name;
                    } else {
                        $anlegen[] = $klasseId;
                    }
                }
            }

            if ($fehler) {
                throw ValidationException::withMessages([
                    'klasse_ids' => 'In ' . $this->klassenListe($fehler) . ' gibt es schon eine Spalte „' . $name
                        . '“ in dieser Gruppe mit einer anderen Art. Bitte zuerst dort umbenennen oder deaktivieren.',
                ]);
            }

            // ── Kollisionen in Klassen, in denen die Spalte aktiv ist/wird ─────
            $deaktivierteIds = collect($deaktivieren)->pluck('id');
            $aktivBleibend = $kopien
                ->filter(fn ($k) => !$k->deactivated_from && !$deaktivierteIds->contains($k->id))
                ->merge($reaktivieren);
            $uebernommeneUids = collect($uebernehmen)->pluck('spalten_uid')->filter()->unique();

            $kollisionen = [];
            foreach ($aktivBleibend as $kopie) {
                $andere = PaedDiaryColumn::where('klasse_id', $kopie->klasse_id)
                    ->whereNull('deactivated_from')
                    ->where('id', '!=', $kopie->id)
                    ->where(fn ($q) => $q->whereNull('spalten_uid')->orWhere('spalten_uid', '!=', $uid))
                    ->gleichBenannt($name, $gruppe)
                    ->exists();
                if ($andere) {
                    $kollisionen[(int) $kopie->klasse_id] = $kopie->klasse_id;
                }
            }
            // Eine übernommene Verknüpfung darf in keiner Klasse neben einer aktiven eigenen Kopie stehen
            if ($uebernommeneUids->isNotEmpty()) {
                $klassenAktiv = $aktivBleibend->pluck('klasse_id')->map(fn ($id) => (int) $id);
                PaedDiaryColumn::whereIn('spalten_uid', $uebernommeneUids)->whereNull('deactivated_from')
                    ->get(['klasse_id'])
                    ->each(function ($k) use ($klassenAktiv, &$kollisionen) {
                        if ($klassenAktiv->contains((int) $k->klasse_id)) {
                            $kollisionen[(int) $k->klasse_id] = $k->klasse_id;
                        }
                    });
            }
            if ($kollisionen) {
                $namen = Klasse::whereIn('id', array_values($kollisionen))->orderBy('name')->pluck('name')->all();
                throw ValidationException::withMessages([
                    'name' => 'In ' . $this->klassenListe($namen) . ' gibt es schon eine andere Spalte „' . $name . '“'
                        . ($gruppe ? ' in der Gruppe „' . $gruppe . '“' : ' ohne Gruppe') . '.',
                ]);
            }

            // ── Änderungen schreiben ─────────────────────────────────────────
            $heute = Carbon::today()->toDateString();
            foreach ($deaktivieren as $kopie) {
                $kopie->deactivated_from = $heute;
                $kopie->save();
            }
            foreach ($reaktivieren as $kopie) {
                $kopie->deactivated_from = null;
                $kopie->save();
            }
            // Übernommene Spalten werden samt ihrer übrigen Kopien mit dieser Spalte verknüpft
            foreach ($uebernehmen as $vorhanden) {
                $vorhanden->kopien()->update(['spalten_uid' => $uid]);
                if (!$vorhanden->spalten_uid) {
                    $vorhanden->update(['spalten_uid' => $uid]);
                }
            }
            if (!$spalte->spalten_uid) {
                $spalte->update(['spalten_uid' => $uid]);
            }

            // Name, Gruppe, Typ für alle Kopien (Gruppenwechsel übernimmt „bis Wochenende“ der neuen Gruppe)
            if ($nameGeaendert || $gruppeGeaendert || $typGeaendert) {
                PaedDiaryColumn::where('spalten_uid', $uid)->orderBy('id')->get()
                    ->each(function (PaedDiaryColumn $kopie) use ($name, $gruppe, $typ) {
                        $kopie->name = $name;
                        $kopie->category = $gruppe;
                        $kopie->type = $typ;
                        $kopie->save();
                    });
                // Weitere, bisher getrennt verknüpfte Spalten mit nun gleichem Name, Typ und Gruppe
                // gehören dazu (ohne Überschneidung, siehe Kollisionsprüfung)
                $this->gleicheVerknuepfen($uid, $name, $typ, $gruppe);
            }

            foreach ($anlegen as $klasseId) {
                PaedDiaryColumn::create([
                    'klasse_id' => $klasseId,
                    'spalten_uid' => $uid,
                    'name' => $name,
                    'slug' => $this->eindeutigerSlug($name, $klasseId),
                    'type' => $typ,
                    'category' => $gruppe,
                    'sort_order' => (int) PaedDiaryColumn::where('klasse_id', $klasseId)->max('sort_order') + 1,
                ]);
            }

            return [
                'neu' => count($anlegen),
                'reaktiviert' => count($reaktivieren),
                'uebernommen' => count($uebernehmen),
                'deaktiviert' => count($deaktivieren),
            ];
        });
    }

    /**
     * Kopie der Spalte in einer Klasse: bevorzugt die aktive, sonst die zuletzt angelegte deaktivierte.
     */
    public function kopieInKlasse(Collection $kopien, int $klasseId): ?PaedDiaryColumn
    {
        $inKlasse = $kopien->filter(fn ($k) => (int) $k->klasse_id === $klasseId);

        return $inKlasse->first(fn ($k) => !$k->deactivated_from) ?? $inKlasse->sortByDesc('id')->first();
    }

    /** Slug, der in der Klasse noch frei ist. */
    public function eindeutigerSlug(string $name, int $klasseId): string
    {
        $basis = Str::slug($name) ?: $name;
        $slug = $basis;
        $zaehler = 2;
        while (PaedDiaryColumn::where('klasse_id', $klasseId)->where('slug', $slug)->exists()) {
            $slug = $basis . '-' . $zaehler++;
        }

        return $slug;
    }

    private function hatWerte(Collection $kopien): bool
    {
        return PaedDiaryColumnValue::whereIn('paed_diary_column_id', $kopien->pluck('id'))->exists();
    }

    private function gleicheVerknuepfen(string $uid, string $name, string $typ, ?string $gruppe): void
    {
        $andere = PaedDiaryColumn::gleichBenannt($name, $gruppe)
            ->where('type', $typ)
            ->where(fn ($q) => $q->whereNull('spalten_uid')->orWhere('spalten_uid', '!=', $uid))
            ->pluck('spalten_uid')->filter()->unique();
        if ($andere->isNotEmpty()) {
            PaedDiaryColumn::whereIn('spalten_uid', $andere)->update(['spalten_uid' => $uid]);
        }
        PaedDiaryColumn::gleichBenannt($name, $gruppe)->where('type', $typ)->whereNull('spalten_uid')
            ->update(['spalten_uid' => $uid]);
    }

    private function anzahlKlassen(int $anzahl): string
    {
        return $anzahl === 1 ? '1 weiteren Klasse' : $anzahl . ' weiteren Klassen';
    }

    private function klassenListe(array $namen): string
    {
        return (count($namen) === 1 ? 'Klasse ' : 'den Klassen ') . implode(', ', $namen);
    }
}
