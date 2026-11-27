<?php

namespace App\Services\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\ErgebnisStimme;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Abstimmung auf einem Board („Punkte kleben“, z. B. am Pädagogischen Tag).
 *
 * - Starten, beenden, Punkte je Person und „verdeckt“ ändern, zurücksetzen: Schreibrecht.
 * - Punkte vergeben: jede Person mit Leserecht, solange die Abstimmung läuft (nicht im Archiv);
 *   mehrere Punkte auf dieselbe Karte sind erlaubt, insgesamt höchstens `punkte`.
 * - Verdeckt: Während sie läuft, sieht jede Person nur ihre eigenen Punkte; nach dem Beenden alle.
 * - Punkte ändern die Board-Version nicht (keine 409-Konflikte beim gleichzeitigen Abstimmen);
 *   Einstellungen schon (stehen in `ergebnis_boards.abstimmung`).
 */
class ErgebnisboardAbstimmungService
{
    public function __construct(private ErgebnisboardService $boards, private ErgebnisboardVerlaufService $verlauf)
    {
    }

    /**
     * @param  array{status?: string, punkte?: int, verdeckt?: bool}  $daten
     */
    public function einstellen(Ergebnisboard $board, array $daten): Ergebnisboard
    {
        $alt = $board->abstimmungsStand();
        $neu = array_merge($alt, array_intersect_key($daten, $alt));
        $neu['punkte'] = max(1, min(10, (int) $neu['punkte']));
        $neu['verdeckt'] = (bool) $neu['verdeckt'];
        if (! in_array($neu['status'], Ergebnisboard::ABSTIMMUNG_STATUS, true)) {
            throw ValidationException::withMessages(['status' => 'Unbekannter Status.']);
        }
        if ($neu === $alt) {
            return $board;
        }

        DB::transaction(function () use ($board, $alt, $neu) {
            $board->abstimmung = $neu;
            $board->version++;
            $board->save();

            // Werden die Punkte je Person gesenkt, bleiben schon vergebene erhalten (Rest = 0).
            if ($alt['status'] !== $neu['status']) {
                $aktion = match ($neu['status']) {
                    'laeuft' => 'abstimmung_gestartet',
                    'beendet' => 'abstimmung_beendet',
                    default => 'abstimmung_ausgeschaltet',
                };
                $this->verlauf->eintragen($board, $aktion, ['punkte' => $neu['punkte'], 'verdeckt' => $neu['verdeckt']]);
            } else {
                $this->verlauf->eintragen($board, 'abstimmung_geaendert', [
                    'aenderungen' => $this->verlauf->unterschiede(
                        ['punkte' => $alt['punkte'], 'verdeckt' => $alt['verdeckt']],
                        ['punkte' => $neu['punkte'], 'verdeckt' => $neu['verdeckt']],
                    ),
                ]);
            }
        });

        return $board;
    }

    /** Alle Punkte löschen (neue Runde). */
    public function zuruecksetzen(Ergebnisboard $board): void
    {
        DB::transaction(function () use ($board) {
            $anzahl = (int) $board->stimmen()->sum('anzahl');
            $board->stimmen()->delete();
            $board->version++;
            $board->save();
            $this->verlauf->eintragen($board, 'abstimmung_zurueckgesetzt', ['anzahl' => $anzahl]);
        });
    }

    /**
     * Punkt setzen (+1) oder zurücknehmen (−1).
     */
    public function stimmen(Ergebnisboard $board, ErgebnisKarte $karte, User $user, int $richtung): void
    {
        $stand = $board->abstimmungsStand();
        if ($stand['status'] !== 'laeuft' || $board->istArchiviert()) {
            throw ValidationException::withMessages(['abstimmung' => 'Die Abstimmung läuft gerade nicht.']);
        }

        DB::transaction(function () use ($board, $karte, $user, $richtung, $stand) {
            $eigene = ErgebnisStimme::query()->where('board_id', $board->id)->where('user_id', $user->id)
                ->whereHas('karte')->lockForUpdate()->get();
            $zeile = $eigene->firstWhere('karte_id', $karte->id);

            if ($richtung > 0) {
                if ((int) $eigene->sum('anzahl') >= $stand['punkte']) {
                    throw ValidationException::withMessages(['abstimmung' => 'Sie haben alle '.$stand['punkte'].' Punkte vergeben. Nehmen Sie einen Punkt zurück, um ihn neu zu setzen.']);
                }
                if ($zeile) {
                    $zeile->increment('anzahl');
                } else {
                    ErgebnisStimme::create(['board_id' => $board->id, 'karte_id' => $karte->id, 'user_id' => $user->id, 'anzahl' => 1]);
                }

                return;
            }

            if (! $zeile) {
                return;
            }
            $zeile->anzahl > 1 ? $zeile->decrement('anzahl') : $zeile->delete();
        });
    }

    /**
     * Stand für die Oberfläche: Summen je Karte (null, solange verdeckt), eigene Punkte, Rest.
     */
    public function stand(Ergebnisboard $board, ?User $user): array
    {
        $stand = $board->abstimmungsStand();
        if ($stand['status'] === 'aus') {
            return $stand + ['karten' => [], 'meine' => [], 'rest' => 0, 'teilnehmende' => 0, 'sichtbar' => true];
        }

        // Stimmen gelöschter Karten zählen nicht mit
        $stimmen = ErgebnisStimme::query()->where('ergebnis_karten_stimmen.board_id', $board->id)
            ->join('ergebnis_karten', 'ergebnis_karten.id', '=', 'ergebnis_karten_stimmen.karte_id')
            ->whereNull('ergebnis_karten.deleted_at')
            ->get(['ergebnis_karten_stimmen.karte_id', 'ergebnis_karten_stimmen.user_id', 'ergebnis_karten_stimmen.anzahl']);

        $meine = $stimmen->where('user_id', $user?->id)->mapWithKeys(fn ($s) => [$s->karte_id => (int) $s->anzahl])->all();
        $verbraucht = array_sum($meine);
        $sichtbar = ! ($stand['verdeckt'] && $stand['status'] === 'laeuft');

        return $stand + [
            'karten' => $sichtbar
                ? $stimmen->groupBy('karte_id')->map(fn ($g) => (int) $g->sum('anzahl'))->all()
                : [],
            'meine' => $meine,
            'rest' => max(0, $stand['punkte'] - $verbraucht),
            'teilnehmende' => $stimmen->pluck('user_id')->unique()->count(),
            'sichtbar' => $sichtbar,
        ];
    }
}
