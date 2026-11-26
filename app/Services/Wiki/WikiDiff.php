<?php

namespace App\Services\Wiki;

use App\Support\WikiHtml;

/**
 * Wortweiser Vergleich zweier Fassungen auf Basis des Klartexts.
 * Erst absatzweise (LCS über Zeilen), geänderte Absätze dann wortweise; Ausgabe HTML-escaped mit <ins>/<del>.
 */
class WikiDiff
{
    private const MAX_ZEILEN = 1500;
    private const MAX_WOERTER = 1200;

    /**
     * @return array{html: string, hinzu: int, weg: int}
     */
    public function vergleich(?string $altHtml, ?string $neuHtml): array
    {
        $alt = $this->zeilen(WikiHtml::klartext($altHtml));
        $neu = $this->zeilen(WikiHtml::klartext($neuHtml));

        $ops = (count($alt) * count($neu) > self::MAX_ZEILEN * self::MAX_ZEILEN / 4)
            ? [['del', $alt], ['ins', $neu]]
            : $this->gruppieren($this->lcs($alt, $neu));

        $html = '';
        $hinzu = 0;
        $weg = 0;
        $anzahl = count($ops);
        for ($i = 0; $i < $anzahl; $i++) {
            [$typ, $zeilen] = $ops[$i];
            if ($typ === 'gleich') {
                // Unveränderte Absätze nur als Kontext (2 vor/nach einer Änderung), den Rest zusammenfassen
                $vorne = $i > 0 ? 2 : 0;
                $hinten = $i < $anzahl - 1 ? 2 : 0;
                if (count($zeilen) > $vorne + $hinten + 1) {
                    $ausgelassen = count($zeilen) - $vorne - $hinten;
                    $zeilen = array_merge(
                        array_slice($zeilen, 0, $vorne),
                        [null],
                        $hinten ? array_slice($zeilen, -$hinten) : []
                    );
                }
                foreach ($zeilen as $z) {
                    $html .= $z === null
                        ? '<p class="wiki-diff-auslassung">… '.$ausgelassen.' unveränderte '.($ausgelassen === 1 ? 'Zeile' : 'Zeilen').' …</p>'
                        : '<p class="wiki-diff-gleich">'.e($z).'</p>';
                }
                continue;
            }
            if ($typ === 'del' && isset($ops[$i + 1]) && $ops[$i + 1][0] === 'ins') {
                $alteZeilen = $zeilen;
                $neueZeilen = $ops[$i + 1][1];
                $i++;
                $paare = max(count($alteZeilen), count($neueZeilen));
                for ($p = 0; $p < $paare; $p++) {
                    $a = $alteZeilen[$p] ?? null;
                    $n = $neueZeilen[$p] ?? null;
                    if ($a !== null && $n !== null) {
                        [$teil, $h, $w] = $this->wortDiff($a, $n);
                        $html .= '<p class="wiki-diff-geaendert">'.$teil.'</p>';
                        $hinzu += $h;
                        $weg += $w;
                    } elseif ($a !== null) {
                        $html .= '<p class="wiki-diff-weg"><del>'.e($a).'</del></p>';
                        $weg += $this->wortzahl($a);
                    } else {
                        $html .= '<p class="wiki-diff-hinzu"><ins>'.e($n).'</ins></p>';
                        $hinzu += $this->wortzahl($n);
                    }
                }
                continue;
            }
            foreach ($zeilen as $z) {
                if ($typ === 'del') {
                    $html .= '<p class="wiki-diff-weg"><del>'.e($z).'</del></p>';
                    $weg += $this->wortzahl($z);
                } else {
                    $html .= '<p class="wiki-diff-hinzu"><ins>'.e($z).'</ins></p>';
                    $hinzu += $this->wortzahl($z);
                }
            }
        }

        return ['html' => $html, 'hinzu' => $hinzu, 'weg' => $weg];
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function wortDiff(string $alt, string $neu): array
    {
        $a = preg_split('/(\s+)/u', $alt, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $n = preg_split('/(\s+)/u', $neu, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (count($a) > self::MAX_WOERTER || count($n) > self::MAX_WOERTER) {
            return ['<del>'.e($alt).'</del> <ins>'.e($neu).'</ins>', $this->wortzahl($neu), $this->wortzahl($alt)];
        }

        $html = '';
        $hinzu = 0;
        $weg = 0;
        foreach ($this->gruppieren($this->lcs($a, $n)) as [$typ, $teile]) {
            $text = e(implode('', $teile));
            $woerter = count(array_filter($teile, fn ($t) => trim($t) !== ''));
            if ($typ === 'gleich') {
                $html .= $text;
            } elseif ($typ === 'del') {
                $html .= '<del>'.$text.'</del>';
                $weg += $woerter;
            } else {
                $html .= '<ins>'.$text.'</ins>';
                $hinzu += $woerter;
            }
        }

        return [$html, $hinzu, $weg];
    }

    /**
     * @param  string[]  $a
     * @param  string[]  $b
     * @return array<int, array{0: string, 1: string}>
     */
    private function lcs(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        $tabelle = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $tabelle[$i][$j] = $a[$i] === $b[$j]
                    ? $tabelle[$i + 1][$j + 1] + 1
                    : max($tabelle[$i + 1][$j], $tabelle[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $ops[] = ['gleich', $a[$i]];
                $i++;
                $j++;
            } elseif ($tabelle[$i + 1][$j] >= $tabelle[$i][$j + 1]) {
                $ops[] = ['del', $a[$i++]];
            } else {
                $ops[] = ['ins', $b[$j++]];
            }
        }
        while ($i < $n) {
            $ops[] = ['del', $a[$i++]];
        }
        while ($j < $m) {
            $ops[] = ['ins', $b[$j++]];
        }

        return $ops;
    }

    /**
     * Fasst aufeinanderfolgende gleiche Operationen zusammen; Löschungen vor Einfügungen.
     *
     * @return array<int, array{0: string, 1: string[]}>
     */
    private function gruppieren(array $ops): array
    {
        $gruppen = [];
        $del = [];
        $ins = [];
        $gleich = [];
        $flush = function () use (&$gruppen, &$del, &$ins, &$gleich) {
            if ($gleich) {
                $gruppen[] = ['gleich', $gleich];
                $gleich = [];
            }
            if ($del) {
                $gruppen[] = ['del', $del];
                $del = [];
            }
            if ($ins) {
                $gruppen[] = ['ins', $ins];
                $ins = [];
            }
        };
        foreach ($ops as [$typ, $wert]) {
            if ($typ === 'gleich') {
                if ($del || $ins) {
                    $flush();
                }
                $gleich[] = $wert;
            } else {
                if ($gleich) {
                    $flush();
                }
                $typ === 'del' ? $del[] = $wert : $ins[] = $wert;
            }
        }
        $flush();

        return $gruppen;
    }

    /**
     * @return string[]
     */
    private function zeilen(string $text): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($z) => $z !== ''));
    }

    private function wortzahl(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY));
    }
}
