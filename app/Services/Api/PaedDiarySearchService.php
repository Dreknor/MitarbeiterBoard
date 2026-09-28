<?php

namespace App\Services\Api;

use App\Models\PaedDiaryEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * API v1: Volltextsuche im Pädagogischen Tagebuch.
 *
 * Der Eintragstext ist in der Datenbank verschlüsselt (PaedDiaryEntry::setContentAttribute), eine
 * Suche per SQL (LIKE/FULLTEXT) ist daher nicht möglich. Die Einträge werden – bereits nach Rechten,
 * Schüler/Klasse, Zeitraum und Kategorie vorgefiltert – blockweise geladen, entschlüsselt und in PHP
 * durchsucht. Damit eine Suche nicht unbegrenzt läuft, werden höchstens MAX_SCANNED Einträge geprüft
 * (neueste zuerst); `meta.search_truncated` zeigt an, dass ältere Einträge nicht durchsucht wurden.
 *
 * Suchregeln: Groß-/Kleinschreibung und Umlaute egal („klettergerust“ findet „Klettergerüst“),
 * alle Suchwörter müssen vorkommen (auch als Wortteil), Füllwörter einer Frage werden ignoriert
 * („Wann hatten wir das mit dem Streit am Klettergerüst?“ → „streit“, „klettergerust“).
 */
class PaedDiarySearchService
{
    public const MAX_SCANNED = 5000;

    private const STOP_WORDS = [
        'ab', 'aber', 'alle', 'als', 'am', 'an', 'auch', 'auf', 'aus', 'bei', 'beim', 'bin', 'bis', 'da', 'das',
        'dass', 'dem', 'den', 'der', 'des', 'die', 'dies', 'diese', 'du', 'ein', 'eine', 'einem', 'einen', 'einer',
        'er', 'es', 'etwas', 'fur', 'gab', 'gibt', 'hat', 'hatte', 'hatten', 'hab', 'habe', 'haben', 'ich', 'ihr',
        'im', 'in', 'ist', 'ja', 'mal', 'man', 'mit', 'nach', 'noch', 'nun', 'ob', 'oder', 'sich', 'sie', 'sind',
        'so', 'um', 'und', 'uns', 'vom', 'von', 'vor', 'wann', 'war', 'waren', 'was', 'wer', 'wie', 'wir', 'wo',
        'zu', 'zum', 'zur',
    ];

    /**
     * Suchbegriff in normalisierte Suchwörter zerlegen (leer = keine sinnvollen Wörter).
     *
     * @return string[]
     */
    public function terms(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $this->normalize($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_unique($words));
        $meaningful = array_values(array_filter(
            $words,
            fn ($w) => !in_array($w, self::STOP_WORDS, true) && mb_strlen($w) >= 2
        ));

        // Nur Füllwörter (z. B. „wann war das“) → dann doch alle Wörter verwenden
        return $meaningful ?: $words;
    }

    public function matches(PaedDiaryEntry $entry, array $terms): bool
    {
        $haystack = $this->normalize(($entry->content ?? '') . ' ' . ($entry->category?->name ?? ''));
        foreach ($terms as $term) {
            if (!str_contains($haystack, $term)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Durchsucht die (sortierte) Abfrage und liefert die passenden Einträge seitenweise.
     *
     * @return array{0: LengthAwarePaginator, 1: array{search_terms: string[], search_truncated: bool}}
     */
    public function paginate(Builder $query, string $search, int $perPage, int $page): array
    {
        $terms = $this->terms($search);
        $matches = collect();
        $scanned = 0;

        if ($terms) {
            foreach ($query->lazy(500) as $entry) {
                if (++$scanned > self::MAX_SCANNED) {
                    break;
                }
                if ($this->matches($entry, $terms)) {
                    $matches->push($entry);
                }
            }
        }

        $paginator = new LengthAwarePaginator(
            $matches->forPage($page, $perPage)->values(),
            $matches->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return [$paginator, [
            'search_terms' => $terms,
            'search_truncated' => $scanned > self::MAX_SCANNED,
        ]];
    }

    private function normalize(string $text): string
    {
        return mb_strtolower(Str::ascii($text));
    }
}
