<?php

namespace App\Services\Ergebnisboards;

use App\Exceptions\VersionKonfliktException;
use App\Models\Ergebnisboard;
use App\Models\ErgebnisBoardZugriff;
use App\Models\ErgebnisKarte;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\User;
use App\Support\ErgebnisboardVorlagen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;

/**
 * Einzige Stelle für alle Regeln der Ergebnisboards: Zugriff, Anlegen, Kopf, Bereiche,
 * Karten (inkl. Sortierung, Zuständige, Anhänge), Zugriffe, Duplizieren, Archivieren.
 *
 * Jede Änderung erhöht `ergebnis_boards.version`; wird eine Version mitgegeben, die nicht
 * mehr aktuell ist, wirft der Service VersionKonfliktException (Controller → 409 + Stand).
 */
class ErgebnisboardService
{
    public const MAX_DATEI_KB = 20480;

    public function __construct(private ErgebnisboardVerlaufService $verlauf)
    {
    }

    /** Typ-Kürzel im Zugriffsformular → Model-Klasse. */
    public const TYPEN = ['user' => User::class, 'group' => Group::class, 'role' => Role::class];

    // ------------------------------------------------------------------
    // Zugriff
    // ------------------------------------------------------------------

    /** Höchstes Recht der Person am Board (lesen|schreiben|verwalten) oder null. */
    public function rechtFuer(Ergebnisboard $board, ?User $user): ?string
    {
        if (! $user) {
            return null;
        }
        if ($user->can(ErgebnisboardVorlagen::PERMISSION_MANAGE) || (int) $board->created_by === (int) $user->id) {
            return 'verwalten';
        }

        $groupIds = $user->groups()->pluck('id')->filter()->all();
        $roleIds = $user->roles()->pluck('id')->all();

        $stufe = 0;
        foreach ($board->zugriffe as $z) {
            $passt = match ($z->berechtigter_type) {
                User::class => (int) $z->berechtigter_id === (int) $user->id,
                Group::class => in_array((int) $z->berechtigter_id, array_map('intval', $groupIds), true),
                Role::class => in_array((int) $z->berechtigter_id, array_map('intval', $roleIds), true),
                default => false,
            };
            if ($passt) {
                $wert = Ergebnisboard::RECHTE[$z->recht] ?? 0;
                // „verwalten“ gibt es nur für Personen
                $stufe = max($stufe, $z->berechtigter_type === User::class ? $wert : min($wert, 2));
            }
        }

        if ($stufe === 0 && $board->meeting_lesen && $board->meeting_id) {
            if (Meeting::query()->visibleTo($user)->whereKey($board->meeting_id)->exists()) {
                $stufe = 1;
            }
        }

        return $stufe > 0 ? array_search($stufe, Ergebnisboard::RECHTE, true) : null;
    }

    public function darf(Ergebnisboard $board, ?User $user, string $recht): bool
    {
        $hat = $this->rechtFuer($board, $user);

        return $hat !== null && Ergebnisboard::RECHTE[$hat] >= Ergebnisboard::RECHTE[$recht];
    }

    /**
     * Personen, die das Board mindestens lesen dürfen (Auswahl für Zuständige).
     *
     * @return Collection<int, User>
     */
    public function personenMitZugriff(Ergebnisboard $board): Collection
    {
        $ids = collect([$board->created_by]);

        foreach ($board->zugriffe as $z) {
            $ids = $ids->merge(match ($z->berechtigter_type) {
                User::class => [$z->berechtigter_id],
                Group::class => DB::table('group_user')->where('group_id', $z->berechtigter_id)->pluck('user_id')->all(),
                Role::class => DB::table('model_has_roles')->where('role_id', $z->berechtigter_id)
                    ->where('model_type', User::class)->pluck('model_id')->all(),
                default => [],
            });
        }

        if ($board->meeting_lesen && $board->meeting) {
            $ids = $ids->merge($this->meetingPersonen($board->meeting));
        }

        return User::query()->whereIn('id', $ids->filter()->unique()->all())->orderBy('name')->get(['id', 'name', 'email']);
    }

    private function meetingPersonen(Meeting $meeting): array
    {
        $ids = [$meeting->creator_id];
        if ($meeting->group_id) {
            $ids = array_merge($ids, DB::table('group_user')->where('group_id', $meeting->group_id)->pluck('user_id')->all());
        }
        foreach ($meeting->participants()->get() as $p) {
            $ids = array_merge($ids, match ($p->participant_type) {
                User::class => [$p->participant_id],
                Group::class => DB::table('group_user')->where('group_id', $p->participant_id)->pluck('user_id')->all(),
                Role::class => DB::table('model_has_roles')->where('role_id', $p->participant_id)->where('model_type', User::class)->pluck('model_id')->all(),
                default => [],
            });
        }

        return $ids;
    }

    // ------------------------------------------------------------------
    // Board
    // ------------------------------------------------------------------

    /**
     * @param  array{titel: string, vorlage: string, untertitel?: ?string, kopfzeile?: ?string, meeting_id?: ?int, meeting_lesen?: bool, theme_id?: ?int, zugriffe?: array}  $daten
     */
    public function anlegen(array $daten, User $user): Ergebnisboard
    {
        $vorlage = ErgebnisboardVorlagen::finden($daten['vorlage'])
            ?? throw ValidationException::withMessages(['vorlage' => 'Unbekannte Vorlage.']);

        return DB::transaction(function () use ($daten, $vorlage, $user) {
            $board = Ergebnisboard::create([
                'titel' => $daten['titel'],
                'untertitel' => $daten['untertitel'] ?? null,
                'kopfzeile' => $daten['kopfzeile'] ?? null,
                'fusszeile' => $daten['fusszeile'] ?? null,
                'vorlage' => $daten['vorlage'],
                'farbschema' => $daten['farbschema'] ?? 'klar',
                'spalten' => $vorlage['spalten'],
                'bereiche' => $vorlage['bereiche'],
                'achsen' => $vorlage['achsen'],
                'legende' => $vorlage['legende'],
                'bereiche_frei' => (bool) $vorlage['bereiche_frei'],
                'auto_status' => (bool) $vorlage['auto_status'],
                'meeting_id' => $daten['meeting_id'] ?? null,
                'meeting_lesen' => (bool) ($daten['meeting_lesen'] ?? false),
                'theme_id' => $daten['theme_id'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->zugriffeSchreiben($board, $daten['zugriffe'] ?? []);
            $this->verlauf->eintragen($board, 'board_angelegt', ['titel' => $board->titel], null, $user);

            return $board;
        });
    }

    /**
     * Kopf, Achsen- und Legendentexte, Farbschema, Verknüpfungen.
     */
    public function aktualisieren(Ergebnisboard $board, array $daten, ?int $version = null): Ergebnisboard
    {
        return $this->mitVersion($board, $version, function (Ergebnisboard $board) use ($daten) {
            $vorher = $this->kopfStand($board);
            $felder = array_intersect_key($daten, array_flip([
                'titel', 'untertitel', 'kopfzeile', 'fusszeile', 'farbschema', 'meeting_id', 'meeting_lesen', 'theme_id',
            ]));
            if (array_key_exists('achsen', $daten) && $board->achsen) {
                $felder['achsen'] = $this->texteUebernehmen($board->achsen, (array) $daten['achsen']);
            }
            if (array_key_exists('legende', $daten) && $board->legende) {
                $legende = $board->legende;
                $legende['titel'] = Str::limit((string) ($daten['legende']['titel'] ?? $legende['titel'] ?? ''), 80, '');
                foreach ($legende['eintraege'] ?? [] as $i => $e) {
                    if (isset($daten['legende']['eintraege'][$i]['text'])) {
                        $legende['eintraege'][$i]['text'] = Str::limit((string) $daten['legende']['eintraege'][$i]['text'], 120, '');
                    }
                }
                $felder['legende'] = $legende;
            }
            $board->fill($felder);

            $nachher = $this->kopfStand($board);
            $aenderungen = $this->verlauf->unterschiede($vorher, $nachher);
            foreach (['achsen', 'legende'] as $f) {
                if (isset($aenderungen[$f])) {
                    $aenderungen[$f] = $this->texteDiff($vorher[$f], $nachher[$f]);
                }
            }
            if ($aenderungen) {
                $this->verlauf->eintragen($board, 'board_geaendert', ['aenderungen' => $aenderungen]);
            }
        });
    }

    /** Vergleichbarer Stand von Kopf und Verknüpfungen (Sitzung/Thema als Titel). */
    private function kopfStand(Ergebnisboard $board): array
    {
        $meeting = $board->meeting_id ? Meeting::find($board->meeting_id) : null;
        $theme = $board->theme_id ? \App\Models\Theme::find($board->theme_id) : null;

        return [
            'titel' => $board->titel,
            'untertitel' => $board->untertitel,
            'kopfzeile' => $board->kopfzeile,
            'fusszeile' => $board->fusszeile,
            'farbschema' => ErgebnisboardVorlagen::FARBSCHEMATA[$board->farbschema] ?? $board->farbschema,
            'meeting' => $meeting ? $this->meetingTitel($meeting) : null,
            'theme' => $theme?->theme,
            'meeting_lesen' => (bool) $board->meeting_lesen,
            'achsen' => $this->texteFlach((array) $board->achsen),
            'legende' => $this->texteFlach((array) $board->legende),
        ];
    }

    /** @return array<string, string> Pfad => Text */
    private function texteFlach(array $werte, string $praefix = ''): array
    {
        $flach = [];
        foreach ($werte as $k => $v) {
            if (is_array($v)) {
                $flach += $this->texteFlach($v, $praefix.$k.'.');
            } elseif (is_string($v) && $k !== 'farbe') {
                $flach[$praefix.$k] = $v;
            }
        }

        return $flach;
    }

    /** Nur die geänderten Einzeltexte (Achsen/Legende) als [alt, neu]. */
    private function texteDiff(array $alt, array $neu): array
    {
        $a = [];
        $n = [];
        foreach ($neu as $pfad => $text) {
            if (($alt[$pfad] ?? '') !== $text) {
                $a[] = $alt[$pfad] ?? '';
                $n[] = $text;
            }
        }

        return [implode(' · ', $a), implode(' · ', $n)];
    }

    /** Übernimmt nur Texte bekannter Schlüssel (Struktur der Achsen bleibt). */
    private function texteUebernehmen(array $alt, array $neu): array
    {
        foreach ($alt as $achse => $werte) {
            foreach ((array) $werte as $k => $v) {
                if (isset($neu[$achse][$k]) && is_string($neu[$achse][$k])) {
                    $alt[$achse][$k] = Str::limit(trim($neu[$achse][$k]), 60, '');
                }
            }
        }

        return $alt;
    }

    /**
     * Bereiche speichern (Liste in Anzeigereihenfolge). Texte/Farbe immer änderbar;
     * Hinzufügen/Entfernen von Hauptbereichen nur bei `bereiche_frei`, Unterbereiche überall.
     * Bereiche mit Karten (oder Unterbereichen) können nicht entfernt werden.
     *
     * @param  array<int, array<string, mixed>>  $bereiche
     */
    public function bereicheSpeichern(Ergebnisboard $board, array $bereiche, ?int $version = null): Ergebnisboard
    {
        return $this->mitVersion($board, $version, function (Ergebnisboard $board) use ($bereiche) {
            $vorher = $board->bereiche;
            $alt = collect($board->bereiche)->keyBy('schluessel');
            $neu = [];
            $gesehen = [];

            foreach ($bereiche as $b) {
                $schluessel = (string) ($b['schluessel'] ?? '');
                $vorhanden = $schluessel !== '' ? $alt->get($schluessel) : null;
                $eltern = $vorhanden['eltern'] ?? ($b['eltern'] ?? null);

                if (! $vorhanden) {
                    if ($eltern === null && ! $board->bereiche_frei) {
                        throw ValidationException::withMessages(['bereiche' => 'Bei dieser Vorlage können keine weiteren Felder angelegt werden.']);
                    }
                    if ($eltern !== null && (! $alt->has($eltern) || ($alt[$eltern]['eltern'] ?? null) !== null)) {
                        throw ValidationException::withMessages(['bereiche' => 'Ein Unterbereich braucht ein gültiges Hauptfeld.']);
                    }
                    $schluessel = $this->neuerSchluessel($b['titel'] ?? 'bereich', array_merge($alt->keys()->all(), $gesehen));
                    $vorhanden = ['schluessel' => $schluessel, 'eltern' => $eltern, 'spalte' => 1, 'zeile' => 1];
                }

                if (in_array($schluessel, $gesehen, true)) {
                    continue;
                }
                $gesehen[] = $schluessel;

                $status = $vorhanden['status'] ?? null;
                if ($board->bereiche_frei && array_key_exists('status', $b)) {
                    $status = in_array($b['status'], ErgebnisboardVorlagen::STATUS, true) ? $b['status'] : null;
                }

                $neu[] = ErgebnisboardVorlagen::bereichNormalisieren(array_merge($vorhanden, [
                    'schluessel' => $schluessel,
                    'titel' => Str::limit(trim((string) ($b['titel'] ?? $vorhanden['titel'] ?? '')), 80, '') ?: 'Feld',
                    'hinweis' => isset($b['hinweis']) ? Str::limit(trim((string) $b['hinweis']), 200, '') ?: null : ($vorhanden['hinweis'] ?? null),
                    'badge' => isset($b['badge']) ? Str::limit(trim((string) $b['badge']), 60, '') ?: null : ($vorhanden['badge'] ?? null),
                    'farbe' => $b['farbe'] ?? $vorhanden['farbe'] ?? 'grau',
                    'raster' => $b['raster'] ?? $vorhanden['raster'] ?? 1,
                    'status' => $status,
                    'eltern' => $eltern,
                ]));
            }

            // Entfernte Bereiche: nur wenn leer und nur, wenn erlaubt
            $entfernt = $alt->keys()->diff($gesehen);
            foreach ($entfernt as $schluessel) {
                $b = $alt[$schluessel];
                if (($b['eltern'] ?? null) === null && ! $board->bereiche_frei) {
                    throw ValidationException::withMessages(['bereiche' => 'Die Felder dieser Vorlage können nicht entfernt werden.']);
                }
                if ($board->karten()->where('bereich', $schluessel)->exists()) {
                    throw ValidationException::withMessages(['bereiche' => '„'.$b['titel'].'“ enthält noch Karten und kann nicht entfernt werden.']);
                }
                if (collect($neu)->contains(fn ($n) => ($n['eltern'] ?? null) === $schluessel)) {
                    throw ValidationException::withMessages(['bereiche' => '„'.$b['titel'].'“ enthält noch Unterbereiche.']);
                }
            }

            if (count(array_filter($neu, fn ($b) => $b['eltern'] === null)) === 0) {
                throw ValidationException::withMessages(['bereiche' => 'Mindestens ein Feld ist nötig.']);
            }

            // Freie Boards: Hauptbereiche nebeneinander in der gespeicherten Reihenfolge
            if ($board->bereiche_frei) {
                $i = 0;
                foreach ($neu as &$b) {
                    if ($b['eltern'] === null) {
                        $b['spalte'] = ++$i;
                        $b['spalten'] = 1;
                        $b['zeile'] = 1;
                        $b['zeilen'] = 1;
                    }
                }
                unset($b);
                $board->spalten = trim(str_repeat('1fr ', min(12, max(1, $i))));
            }

            $board->bereiche = $neu;

            $diff = $this->verlauf->felderDiff($vorher, $neu);
            if ($diff) {
                $this->verlauf->eintragen($board, 'felder_geaendert', $diff);
            }
        });
    }

    private function neuerSchluessel(string $titel, array $belegt): string
    {
        $basis = Str::limit(Str::slug($titel, '_'), 40, '') ?: 'bereich';
        $schluessel = $basis;
        $n = 2;
        while (in_array($schluessel, $belegt, true)) {
            $schluessel = $basis.'_'.$n++;
        }

        return $schluessel;
    }

    public function archivieren(Ergebnisboard $board, bool $archivieren = true): void
    {
        if ($board->istArchiviert() === $archivieren) {
            return;
        }
        $board->archiviert_at = $archivieren ? now() : null;
        $board->version++;
        $board->save();
        $this->verlauf->eintragen($board, $archivieren ? 'board_archiviert' : 'board_wiederhergestellt');
    }

    public function loeschen(Ergebnisboard $board): void
    {
        DB::transaction(function () use ($board) {
            $board->karten()->withTrashed()->get()->each(fn (ErgebnisKarte $k) => $k->clearMediaCollection(ErgebnisKarte::ANHAENGE));
            $board->delete();
        });
    }

    /** Kopie als Grundlage für den nächsten Anlass – Karten ohne Aufgaben, Anhänge, Zuständige. */
    public function duplizieren(Ergebnisboard $board, User $user): Ergebnisboard
    {
        return DB::transaction(function () use ($board, $user) {
            $kopie = $board->replicate(['version', 'archiviert_at', 'created_by']);
            $kopie->titel = Str::limit($board->titel.' (Kopie)', 255, '');
            $kopie->created_by = $user->id;
            $kopie->version = 1;
            $kopie->archiviert_at = null;
            $kopie->abstimmung = null;
            $kopie->save();

            foreach ($board->karten as $karte) {
                $neu = $karte->replicate(['task_id', 'erinnert_am']);
                $neu->board_id = $kopie->id;
                $neu->task_id = null;
                $neu->save();
            }

            foreach ($board->zugriffe as $z) {
                if ($z->berechtigter_type === User::class && (int) $z->berechtigter_id === (int) $user->id) {
                    continue; // neue:r Ersteller:in hat ohnehin „verwalten“
                }
                $kopie->zugriffe()->create($z->only(['berechtigter_type', 'berechtigter_id', 'recht']));
            }
            // Ersteller:in des Originals behält Zugriff auf die Kopie
            if ($board->created_by && (int) $board->created_by !== (int) $user->id) {
                $kopie->zugriffe()->updateOrCreate(
                    ['berechtigter_type' => User::class, 'berechtigter_id' => $board->created_by],
                    ['recht' => 'verwalten']
                );
            }

            $this->verlauf->eintragen($kopie, 'board_dupliziert', ['quelle' => $board->titel, 'quelle_id' => $board->id], null, $user);

            return $kopie;
        });
    }

    // ------------------------------------------------------------------
    // Zugriffe
    // ------------------------------------------------------------------

    /**
     * @param  array<int, array{typ: string, id: int, recht: string}>  $eintraege
     */
    public function zugriffeSpeichern(Ergebnisboard $board, array $eintraege): Ergebnisboard
    {
        return $this->mitVersion($board, null, function (Ergebnisboard $board) use ($eintraege) {
            $vorher = $this->zugriffeBeschrieben($board->zugriffe()->get());
            $this->zugriffeSchreiben($board, $eintraege, true);
            $nachher = $this->zugriffeBeschrieben($board->zugriffe()->get());

            $geaendert = array_filter(array_keys(array_intersect_key($nachher, $vorher)), fn ($k) => $nachher[$k] !== $vorher[$k]);
            $diff = array_filter([
                'hinzugefuegt' => array_values(array_diff_key($nachher, $vorher)),
                'entfernt' => array_values(array_diff_key($vorher, $nachher)),
                'angepasst' => array_values(array_map(fn ($k) => $nachher[$k].' (vorher '.$vorher[$k].')', $geaendert)),
            ]);
            if ($diff) {
                $this->verlauf->eintragen($board, 'zugriffe_geaendert', $diff);
            }
        });
    }

    /** @return array<string, string> Typ#ID => „Person Anna Muster: schreiben“ */
    private function zugriffeBeschrieben(Collection $zugriffe): array
    {
        $art = [User::class => 'Person', Group::class => 'Gruppe', Role::class => 'Rolle'];
        $liste = [];
        foreach ($zugriffe as $z) {
            $name = $z->berechtigter_type::query()->whereKey($z->berechtigter_id)->value('name') ?? '#'.$z->berechtigter_id;
            $liste[$z->berechtigter_type.'#'.$z->berechtigter_id] = ($art[$z->berechtigter_type] ?? 'Eintrag').' '.$name.': '.$z->recht;
        }

        return $liste;
    }

    private function zugriffeSchreiben(Ergebnisboard $board, array $eintraege, bool $ersetzen = false): void
    {
        if ($ersetzen) {
            $board->zugriffe()->delete();
        }

        $zeilen = [];
        foreach ($eintraege as $e) {
            $klasse = self::TYPEN[$e['typ'] ?? ''] ?? null;
            $id = (int) ($e['id'] ?? 0);
            $recht = $e['recht'] ?? 'lesen';
            if (! $klasse || $id <= 0 || ! isset(Ergebnisboard::RECHTE[$recht])) {
                continue;
            }
            if ($klasse !== User::class && $recht === 'verwalten') {
                $recht = 'schreiben';
            }
            if ($klasse === User::class && $id === (int) $board->created_by) {
                continue;
            }
            $schluessel = $klasse.'#'.$id;
            if (! isset($zeilen[$schluessel]) || Ergebnisboard::RECHTE[$recht] > Ergebnisboard::RECHTE[$zeilen[$schluessel]['recht']]) {
                $zeilen[$schluessel] = ['berechtigter_type' => $klasse, 'berechtigter_id' => $id, 'recht' => $recht];
            }
        }

        foreach ($zeilen as $zeile) {
            if ($zeile['berechtigter_type']::query()->whereKey($zeile['berechtigter_id'])->exists()) {
                $board->zugriffe()->create($zeile);
            }
        }
        $board->unsetRelation('zugriffe');
    }

    // ------------------------------------------------------------------
    // Karten
    // ------------------------------------------------------------------

    /**
     * Karte anlegen (null) oder ändern. `zustaendige` = User-IDs mit Lesezugriff auf das Board.
     */
    public function karteSpeichern(Ergebnisboard $board, ?ErgebnisKarte $karte, array $daten, ?int $version = null, ?User $durch = null): ErgebnisKarte
    {
        $ergebnis = null;
        $neuZugewiesen = [];
        $this->mitVersion($board, $version, function (Ergebnisboard $board) use ($karte, $daten, &$ergebnis, &$neuZugewiesen, $durch) {
            $bereich = $daten['bereich'] ?? $karte?->bereich;
            if (! $bereich || ! $board->bereich($bereich)) {
                throw ValidationException::withMessages(['bereich' => 'Dieses Feld gibt es auf dem Board nicht.']);
            }

            $karte ??= new ErgebnisKarte(['board_id' => $board->id]);
            $neu = ! $karte->exists;
            $vorher = $neu ? null : $this->karteStand($karte);
            if ($neu || $karte->bereich !== $bereich) {
                $karte->sort = (int) $board->karten()->where('bereich', $bereich)->max('sort') + 1;
            }

            $karte->fill(array_intersect_key($daten, array_flip(['titel', 'zusatz', 'etikett', 'fokus', 'breit', 'faellig_am'])));
            if (array_key_exists('beschreibung', $daten)) {
                $karte->beschreibung = $this->beschreibungBereinigen($daten['beschreibung']);
            }
            if (array_key_exists('checkliste', $daten)) {
                $karte->checkliste = $this->checklisteNormalisieren($daten['checkliste']);
            }
            $karte->bereich = $bereich;
            if ($karte->isDirty('faellig_am')) {
                $karte->erinnert_am = null;
            }
            $karte->save();

            if (array_key_exists('zustaendige', $daten)) {
                $erlaubt = $this->personenMitZugriff($board)->pluck('id')->all();
                $ids = collect((array) $daten['zustaendige'])->map(fn ($id) => (int) $id)->unique();
                $fremd = $ids->diff($erlaubt);
                if ($fremd->isNotEmpty()) {
                    throw ValidationException::withMessages(['zustaendige' => 'Zuständig können nur Personen mit Zugriff auf das Board sein.']);
                }
                $sync = $karte->zustaendige()->sync($ids->all());
                $neuZugewiesen = $sync['attached'] ?? [];
                if (! $neu && (! empty($sync['attached']) || ! empty($sync['detached']))) {
                    $namen = fn (array $ids) => User::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
                    $this->verlauf->karte($board, $karte, 'zustaendige_geaendert', array_filter([
                        'hinzugefuegt' => $namen($sync['attached'] ?? []),
                        'entfernt' => $namen($sync['detached'] ?? []),
                    ]), $durch);
                }
            }

            if ($neu) {
                $this->verlauf->karte($board, $karte, 'karte_angelegt', array_filter([
                    'feld' => $this->bereichTitel($board, $karte->bereich),
                    'hinzugefuegt' => $karte->zustaendige()->pluck('name')->all(),
                ]), $durch);
            } else {
                $nachher = $this->karteStand($karte);
                $aenderungen = $this->verlauf->unterschiede(
                    array_diff_key($vorher, ['bereich' => 1]),
                    array_diff_key($nachher, ['bereich' => 1])
                );
                if ($aenderungen) {
                    $this->verlauf->karte($board, $karte, 'karte_geaendert', ['aenderungen' => $aenderungen], $durch);
                }
                if ($vorher['bereich'] !== $nachher['bereich']) {
                    $this->verlauf->karte($board, $karte, 'karte_verschoben', [
                        'von' => $this->bereichTitel($board, $vorher['bereich']),
                        'nach' => $this->bereichTitel($board, $nachher['bereich']),
                    ], $durch);
                }
            }

            $ergebnis = $karte;
        });

        $this->zugewieseneBenachrichtigen($ergebnis, $neuZugewiesen, $durch);

        return $ergebnis;
    }

    /** Neu eingetragene Zuständige benachrichtigen (nicht die Person, die eingetragen hat). */
    private function zugewieseneBenachrichtigen(ErgebnisKarte $karte, array $userIds, ?User $durch): void
    {
        $empfaenger = User::query()->whereIn('id', $userIds)
            ->when($durch, fn ($q) => $q->where('id', '!=', $durch->id))->get();
        if ($empfaenger->isEmpty()) {
            return;
        }
        $karte->loadMissing('board');
        \Illuminate\Support\Facades\Notification::send($empfaenger, new \App\Notifications\ErgebnisKarteZugewiesen($karte, $durch?->name));
    }

    /**
     * Täglich (Scheduler): Zuständige an Karten erinnern, die heute oder morgen fällig sind
     * und nicht in einem „erledigt“-Feld liegen. Je Karte und Fälligkeit nur einmal.
     */
    public function erinnerungenVersenden(): int
    {
        $anzahl = 0;
        ErgebnisKarte::query()
            ->whereNotNull('faellig_am')
            ->whereBetween('faellig_am', [today()->toDateString(), today()->addDay()->toDateString()])
            ->whereNull('erinnert_am')
            ->whereHas('board', fn ($b) => $b->whereNull('archiviert_at'))
            ->with(['board', 'zustaendige'])
            ->get()
            ->each(function (ErgebnisKarte $karte) use (&$anzahl) {
                if (($karte->board->bereich($karte->bereich)['status'] ?? null) === 'erledigt' || $karte->zustaendige->isEmpty()) {
                    return;
                }
                \Illuminate\Support\Facades\Notification::send($karte->zustaendige, new \App\Notifications\ErgebnisKarteFaellig($karte));
                $karte->forceFill(['erinnert_am' => today()])->saveQuietly();
                $anzahl++;
            });

        return $anzahl;
    }

    public function karteLoeschen(Ergebnisboard $board, ErgebnisKarte $karte, ?int $version = null): Ergebnisboard
    {
        return $this->mitVersion($board, $version, function (Ergebnisboard $board) use ($karte) {
            $karte->delete();
            $this->verlauf->karte($board, $karte, 'karte_geloescht', ['feld' => $this->bereichTitel($board, $karte->bereich)]);
        });
    }

    /**
     * Gelöschte Karte zurückholen – ans Ende ihres Feldes; gibt es das Feld nicht mehr,
     * ins erste Hauptfeld.
     */
    public function karteWiederherstellen(Ergebnisboard $board, ErgebnisKarte $karte): Ergebnisboard
    {
        return $this->mitVersion($board, null, function (Ergebnisboard $board) use ($karte) {
            $ersatz = ! $board->bereich($karte->bereich);
            if ($ersatz) {
                $karte->bereich = collect($board->bereiche)->first(fn ($b) => ($b['eltern'] ?? null) === null)['schluessel'];
            }
            $karte->sort = (int) $board->karten()->where('bereich', $karte->bereich)->max('sort') + 1;
            $karte->erinnert_am = null;
            $karte->restore();

            $this->verlauf->karte($board, $karte, 'karte_wiederhergestellt', array_filter([
                'feld' => $this->bereichTitel($board, $karte->bereich),
                'ersatzfeld' => $ersatz,
            ]));
        });
    }

    /**
     * Täglich (Scheduler): seit mehr als AUFBEWAHRUNG_TAGE gelöschte Karten endgültig entfernen
     * (Anhänge, Kommentare, Zuständige). Der Verlaufseintrag „gelöscht“ bleibt stehen.
     */
    public function geloeschteKartenBereinigen(): int
    {
        $anzahl = 0;
        ErgebnisKarte::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays(ErgebnisKarte::AUFBEWAHRUNG_TAGE))
            ->orderBy('id')
            ->each(function (ErgebnisKarte $karte) use (&$anzahl) {
                DB::transaction(function () use ($karte) {
                    $karte->clearMediaCollection(ErgebnisKarte::ANHAENGE);
                    $karte->kommentare()->withTrashed()->forceDelete();
                    $karte->zustaendige()->detach();
                    $karte->forceDelete();
                });
                $anzahl++;
            });

        return $anzahl;
    }

    /** Vergleichbarer Stand einer Karte für den Verlauf. */
    private function karteStand(ErgebnisKarte $karte): array
    {
        return [
            'titel' => $karte->titel,
            'zusatz' => $karte->zusatz,
            'etikett' => $karte->etikett,
            'fokus' => (bool) $karte->fokus,
            'breit' => (bool) $karte->breit,
            'faellig_am' => $karte->faellig_am?->toDateString(),
            'beschreibung' => $karte->beschreibung ? Str::limit($this->klartext($karte->beschreibung), 200) : null,
            'checkliste' => $karte->checkliste
                ? $this->checklisteFortschritt($karte->checkliste).' erledigt ('.implode(', ', array_column($karte->checkliste, 'text')).')'
                : null,
            'bereich' => $karte->bereich,
        ];
    }

    /** Rich-Text der Beschreibung bereinigen (HTMLPurifier); leerer Inhalt → null. */
    private function beschreibungBereinigen(?string $html): ?string
    {
        $sauber = trim(\App\Support\HtmlSanitizer::clean($html));
        if ($sauber === '' || ($this->klartext($sauber) === '' && ! str_contains($sauber, '<img') && ! str_contains($sauber, '<table'))) {
            return null;
        }

        return $sauber;
    }

    public function klartext(?string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ["\n", "\n", "\n", "\n", "\n"], (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', preg_replace("/\n{3,}/", "\n\n", $text)));
    }

    /**
     * Checkliste: höchstens 50 Punkte, leere entfernt, jede mit stabiler ID.
     *
     * @return array<int, array{id: string, text: string, erledigt: bool}>|null
     */
    private function checklisteNormalisieren(mixed $liste): ?array
    {
        $punkte = [];
        $liste = (array) $liste;
        ksort($liste); // validated() liefert verschachtelte Listen nicht in Eingabereihenfolge
        foreach (array_slice($liste, 0, 50) as $p) {
            $text = Str::limit(trim((string) ($p['text'] ?? '')), 255, '');
            if ($text === '') {
                continue;
            }
            $id = preg_match('/^[A-Za-z0-9]{1,20}$/', (string) ($p['id'] ?? '')) ? (string) $p['id'] : Str::random(8);
            $punkte[] = ['id' => $id, 'text' => $text, 'erledigt' => (bool) ($p['erledigt'] ?? false)];
        }

        return $punkte ?: null;
    }

    private function checklisteFortschritt(array $liste): string
    {
        return count(array_filter($liste, fn ($p) => ! empty($p['erledigt']))).'/'.count($liste);
    }

    /** Feldtitel inkl. Hauptfeld („Cream of the Crop › Ganztag leben“). */
    public function bereichTitel(Ergebnisboard $board, ?string $schluessel): string
    {
        $b = $schluessel ? $board->bereich($schluessel) : null;
        if (! $b) {
            return $schluessel ?? '?';
        }
        $eltern = ($b['eltern'] ?? null) ? $board->bereich($b['eltern']) : null;

        return $eltern ? $eltern['titel'].' › '.$b['titel'] : $b['titel'];
    }

    /**
     * Verschieben + Reihenfolge in einem Schritt: [bereich => [karten-ids in Reihenfolge]].
     * Nicht genannte Karten bleiben, wo sie sind.
     */
    public function sortieren(Ergebnisboard $board, array $ordnung, ?int $version = null): Ergebnisboard
    {
        return $this->mitVersion($board, $version, function (Ergebnisboard $board) use ($ordnung) {
            $karten = $board->karten()->get()->keyBy('id');
            foreach ($ordnung as $bereich => $ids) {
                if (! $board->bereich((string) $bereich)) {
                    throw ValidationException::withMessages(['ordnung' => 'Unbekanntes Feld.']);
                }
                foreach (array_values((array) $ids) as $pos => $id) {
                    $karte = $karten->get((int) $id);
                    if (! $karte) {
                        continue;
                    }
                    if ($karte->bereich !== (string) $bereich) {
                        $this->verlauf->karte($board, $karte, 'karte_verschoben', [
                            'von' => $this->bereichTitel($board, $karte->bereich),
                            'nach' => $this->bereichTitel($board, (string) $bereich),
                        ]);
                    }
                    $karte->bereich = (string) $bereich;
                    $karte->sort = $pos + 1;
                    $karte->save();
                }
            }
        });
    }

    // ------------------------------------------------------------------
    // Anhänge
    // ------------------------------------------------------------------

    public function anhangHinzufuegen(Ergebnisboard $board, ErgebnisKarte $karte, UploadedFile $datei): Media
    {
        $media = $karte->addMedia($datei)
            ->usingFileName(Str::random(8).'_'.Str::slug(pathinfo($datei->getClientOriginalName(), PATHINFO_FILENAME)).'.'.strtolower($datei->getClientOriginalExtension() ?: 'bin'))
            ->usingName($datei->getClientOriginalName())
            ->toMediaCollection(ErgebnisKarte::ANHAENGE);
        $this->versionErhoehen($board);
        $this->verlauf->karte($board, $karte, 'anhang_hinzugefuegt', ['datei' => $media->name ?: $media->file_name]);

        return $media;
    }

    public function anhangLoeschen(Ergebnisboard $board, Media $media): void
    {
        $karte = ErgebnisKarte::withTrashed()->find($media->model_id);
        $name = $media->name ?: $media->file_name;
        $media->delete();
        $this->versionErhoehen($board);
        if ($karte) {
            $this->verlauf->karte($board, $karte, 'anhang_entfernt', ['datei' => $name]);
        }
    }

    // ------------------------------------------------------------------
    // Darstellung
    // ------------------------------------------------------------------

    /** Gemeinsame JSON-Darstellung für Alpine, PDF und Tests. */
    public function toArray(Ergebnisboard $board, ?User $user): array
    {
        $board->loadMissing(['karten.zustaendige', 'karten.media', 'karten.task.taskUsers', 'zugriffe', 'meeting', 'theme', 'ersteller']);
        $board->karten->loadCount('kommentare');
        $recht = $this->rechtFuer($board, $user);

        return [
            'id' => $board->id,
            'titel' => $board->titel,
            'untertitel' => $board->untertitel,
            'kopfzeile' => $board->kopfzeile,
            'fusszeile' => $board->fusszeile,
            'vorlage' => $board->vorlage,
            'vorlage_titel' => $board->vorlageTitel(),
            'farbschema' => $board->farbschema,
            'spalten' => $board->spalten,
            'bereiche' => $board->bereiche,
            'bereiche_frei' => $board->bereiche_frei,
            'auto_status' => $board->auto_status,
            'achsen' => $board->achsen,
            'legende' => $board->legende,
            'version' => $board->version,
            'archiviert' => $board->istArchiviert(),
            'meeting' => $board->meeting ? ['id' => $board->meeting->id, 'titel' => $this->meetingTitel($board->meeting), 'url' => route('meetings.show', $board->meeting->id)] : null,
            'meeting_lesen' => $board->meeting_lesen,
            'theme' => $board->theme ? ['id' => $board->theme->id, 'titel' => $board->theme->theme] : null,
            'ersteller' => $board->ersteller?->name,
            'aktualisiert' => $board->updated_at?->toIso8601String(),
            'recht' => $recht,
            'darf_schreiben' => $recht !== null && Ergebnisboard::RECHTE[$recht] >= 2 && ! $board->istArchiviert(),
            'darf_verwalten' => $recht === 'verwalten',
            'darf_kommentieren' => $recht !== null && ! $board->istArchiviert(),
            'kommentare_board' => $board->kommentare()->whereNull('karte_id')->count(),
            'abstimmung' => app(ErgebnisboardAbstimmungService::class)->stand($board, $user),
            'karten' => $board->karten->map(fn (ErgebnisKarte $k) => $this->karteArray($board, $k))->values()->all(),
        ];
    }

    public function karteArray(Ergebnisboard $board, ErgebnisKarte $k): array
    {
        return [
            'id' => $k->id,
            'bereich' => $k->bereich,
            'titel' => $k->titel,
            'zusatz' => $k->zusatz,
            'etikett' => $k->etikett,
            'fokus' => $k->fokus,
            'breit' => $k->breit,
            'sort' => $k->sort,
            'faellig_am' => $k->faellig_am?->toDateString(),
            'ueberfaellig' => $k->faellig_am !== null && $k->faellig_am->isPast() && ! $k->faellig_am->isToday()
                && ($board->bereich($k->bereich)['status'] ?? null) !== 'erledigt',
            'zustaendige' => $k->zustaendige->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'kuerzel' => $this->kuerzel($u->name),
            ])->values()->all(),
            'anhaenge' => $k->getMedia(ErgebnisKarte::ANHAENGE)->map(fn (Media $m) => [
                'id' => $m->id,
                'name' => $m->name ?: $m->file_name,
                'groesse' => $m->size,
                'mime' => $m->mime_type,
                'url' => route('ergebnisboards.karten.dateien.show', [$board->id, $k->id, $m->id]),
            ])->values()->all(),
            'aufgabe' => $this->aufgabeArray($k),
            'kommentare' => (int) ($k->kommentare_count ?? $k->kommentare()->count()),
            'beschreibung' => $k->beschreibung,
            'beschreibung_text' => $k->beschreibung ? Str::limit($this->klartext($k->beschreibung), 500) : null,
            'checkliste' => $k->checkliste ?? [],
        ];
    }

    private function aufgabeArray(ErgebnisKarte $k): ?array
    {
        $task = $k->task;
        if (! $task) {
            return null;
        }
        $gesamt = $task->isPersonal() ? 1 : $task->taskUsers->count();
        $erledigt = $task->isPersonal() ? ($task->completed ? 1 : 0) : $task->taskUsers->whereNotNull('completed_at')->count();

        return [
            'id' => $task->id,
            'erledigt' => (bool) $task->completed,
            'erledigt_am' => $task->completed_at?->toDateString(),
            'fortschritt' => $gesamt > 1 ? $erledigt.'/'.$gesamt : null,
            'datum' => $task->date?->toDateString(),
        ];
    }

    /**
     * Entwurf für eine Nachricht (Posts) zum Teilen eines Boards: Titel, Kurzfassung und
     * HTML-Text mit Link und den Karten je Feld (Fokus-Karten markiert).
     *
     * @return array{header: string, kurzfassung: ?string, text: string}
     */
    public function nachrichtEntwurf(Ergebnisboard $board): array
    {
        $board->loadMissing('karten');
        $e = fn (?string $t) => e((string) $t);
        $html = '<p>'.$e($board->untertitel ?: 'Ergebnisse im Überblick').' – <a href="'.e(route('ergebnisboards.show', $board)).'">Board öffnen</a></p>';

        foreach ($board->bereiche as $bereich) {
            $karten = $board->karten->where('bereich', $bereich['schluessel']);
            if ($karten->isEmpty()) {
                continue;
            }
            $html .= '<h3>'.$e($bereich['titel']).'</h3><ul>';
            foreach ($karten as $k) {
                $html .= '<li>'.($k->fokus ? '★ ' : '').'<strong>'.$e($k->titel).'</strong>'.($k->zusatz ? ' – '.$e($k->zusatz) : '').'</li>';
            }
            $html .= '</ul>';
        }

        return [
            'header' => Str::limit($board->titel, 250, ''),
            'kurzfassung' => $board->untertitel ? Str::limit($board->untertitel, 500, '') : null,
            'text' => $html,
        ];
    }

    public function meetingTitel(Meeting $meeting): string
    {
        $datum = $meeting->date ? \Illuminate\Support\Carbon::parse($meeting->date)->format('d.m.Y') : null;

        return trim(($meeting->title ?: 'Sitzung').($datum ? ' ('.$datum.')' : ''));
    }

    public function kuerzel(string $name): string
    {
        $teile = preg_split('/[\s,.-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
        $k = mb_substr($teile[0], 0, 1).(count($teile) > 1 ? mb_substr(end($teile), 0, 1) : mb_substr($teile[0], 1, 1));

        return mb_strtoupper($k);
    }

    /**
     * Auswahl für das Zugriffsformular und aktuelle Einträge.
     */
    public function zugriffsOptionen(): array
    {
        return [
            'users' => User::query()->orderBy('name')->get(['id', 'name'])->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()->all(),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name'])->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name])->values()->all(),
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name'])->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name])->values()->all(),
        ];
    }

    /** @return array<int, array{typ: string, id: int, recht: string}> */
    public function zugriffsAuswahl(?Ergebnisboard $board): array
    {
        if (! $board) {
            return [];
        }
        $typen = array_flip(self::TYPEN);

        return $board->zugriffe->map(fn (ErgebnisBoardZugriff $z) => [
            'typ' => $typen[$z->berechtigter_type] ?? 'user',
            'id' => (int) $z->berechtigter_id,
            'recht' => $z->recht,
        ])->values()->all();
    }

    // ------------------------------------------------------------------
    // intern
    // ------------------------------------------------------------------

    /**
     * Führt eine Änderung unter Versionsprüfung aus und erhöht die Version.
     */
    private function mitVersion(Ergebnisboard $board, ?int $version, callable $aenderung): Ergebnisboard
    {
        return DB::transaction(function () use ($board, $version, $aenderung) {
            $aktuell = Ergebnisboard::query()->lockForUpdate()->findOrFail($board->id);
            if ($version !== null && $version !== (int) $aktuell->version) {
                throw new VersionKonfliktException('Das Board wurde zwischenzeitlich geändert.');
            }
            $board->version = (int) $aktuell->version;

            $aenderung($board);

            $board->version = (int) $aktuell->version + 1;
            $board->save();
            $board->unsetRelation('karten');

            return $board;
        });
    }

    public function versionErhoehen(Ergebnisboard $board): void
    {
        Ergebnisboard::query()->whereKey($board->id)->increment('version');
        $board->refresh();
    }
}
