<?php

namespace App\Services\Arbeitsblaetter;

use App\Models\Arbeitsblaetter\AbKiVerbrauch;
use App\Models\User;
use App\Notifications\AbKiBudgetWarnung;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Monatslimit der KI-Erkennung (Seiten und Euro, Einstellungen `ab_ki_limit_*`).
 *
 * - Warnung ab `ab_ki_warnung_prozent` (einmal pro Monat an alle mit `edit settings`),
 * - Sperre bei 100 % – der manuelle Weg (Editor mit Original daneben) bleibt immer offen.
 */
class AbKiBudget
{
    public function monat(): string
    {
        return now()->format('Y-m');
    }

    /** @return array{seiten: int, kosten_cent: int, aufrufe: int} */
    public function verbrauch(?string $monat = null): array
    {
        $zeile = AbKiVerbrauch::where('monat', $monat ?? $this->monat())
            ->selectRaw('COALESCE(SUM(seiten), 0) as seiten, COALESCE(SUM(kosten_cent), 0) as kosten, COUNT(*) as aufrufe')
            ->first();

        return ['seiten' => (int) $zeile->seiten, 'kosten_cent' => (int) $zeile->kosten, 'aufrufe' => (int) $zeile->aufrufe];
    }

    /** @return array{seiten: int, cent: int} 0 = ohne Limit */
    public function limits(): array
    {
        return [
            'seiten' => max(0, (int) settings('ab_ki_limit_seiten')),
            'cent' => max(0, (int) round(((float) str_replace(',', '.', (string) settings('ab_ki_limit_euro'))) * 100)),
        ];
    }

    /** Höchster Ausschöpfungsgrad (0–100+) über beide Limits. */
    public function prozent(?array $verbrauch = null): int
    {
        $verbrauch ??= $this->verbrauch();
        $limits = $this->limits();
        $werte = [];
        if ($limits['seiten'] > 0) {
            $werte[] = $verbrauch['seiten'] / $limits['seiten'] * 100;
        }
        if ($limits['cent'] > 0) {
            $werte[] = $verbrauch['kosten_cent'] / $limits['cent'] * 100;
        }

        return $werte ? (int) floor(max($werte)) : 0;
    }

    /** Darf (noch) erkannt werden? `$seiten` = Seiten des neuen Blatts. */
    public function darfErkennen(int $seiten = 1): bool
    {
        $verbrauch = $this->verbrauch();
        $limits = $this->limits();
        if ($limits['seiten'] > 0 && $verbrauch['seiten'] + $seiten > $limits['seiten']) {
            return false;
        }
        if ($limits['cent'] > 0 && $verbrauch['kosten_cent'] >= $limits['cent']) {
            return false;
        }

        return true;
    }

    public function sperrgrund(int $seiten = 1): ?string
    {
        return $this->darfErkennen($seiten)
            ? null
            : 'Das KI-Kontingent für diesen Monat ist aufgebraucht. Das Blatt kann manuell erfasst werden.';
    }

    public function verbuchen(?int $blattId, ?int $userId, int $seiten, int $kostenCent, string $anbieter): void
    {
        $vorher = $this->prozent();
        AbKiVerbrauch::create([
            'monat' => $this->monat(),
            'blatt_id' => $blattId,
            'user_id' => $userId,
            'seiten' => $seiten,
            'kosten_cent' => $kostenCent,
            'anbieter' => $anbieter,
        ]);
        $nachher = $this->prozent();

        $schwelle = max(1, min(100, (int) (settings('ab_ki_warnung_prozent') ?: 80)));
        foreach ([100, $schwelle] as $grenze) {
            if ($vorher < $grenze && $nachher >= $grenze) {
                $this->warnen($nachher, $grenze >= 100);
                break;
            }
        }
    }

    private function warnen(int $prozent, bool $gesperrt): void
    {
        $schluessel = 'ab_ki_warnung_'.$this->monat().($gesperrt ? '_sperre' : '');
        if (! Cache::add($schluessel, true, now()->addDays(40))) {
            return;
        }
        $empfaenger = User::permission('edit settings')->get();
        if ($empfaenger->isNotEmpty()) {
            Notification::send($empfaenger, new AbKiBudgetWarnung($prozent, $gesperrt));
        }
    }
}
