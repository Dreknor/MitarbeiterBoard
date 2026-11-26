<?php

namespace App\Services\Wiki;

use App\Models\User;
use App\Models\WikiSeite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Volltextsuche im Wiki: Titel (stark gewichtet), Tags und Klartext (`inhalt_text`), nur sichtbare Seiten.
 * Alle Suchwörter müssen vorkommen; Treffer mit hervorgehobenem Textausschnitt.
 */
class WikiSuche
{
    /**
     * @return array<int, array{id: int, titel: string, url: string, pfad: string, snippet: string, tags: array, geaendert: ?string}>
     */
    public function suchen(User $user, string $begriff, int $limit = 50): array
    {
        $woerter = $this->woerter($begriff);
        if (empty($woerter)) {
            return [];
        }

        $sichtbar = WikiZugriff::sichtbareIds($user);
        if (empty($sichtbar)) {
            return [];
        }

        $query = WikiSeite::query()->whereIn('id', array_keys($sichtbar))->with('tags:id,name,slug');
        foreach ($woerter as $wort) {
            $like = '%'.$this->escape($wort).'%';
            $query->where(fn (Builder $q) => $q->where('titel', 'like', $like)
                ->orWhere('inhalt_text', 'like', $like)
                ->orWhereHas('tags', fn (Builder $t) => $t->where('name', 'like', $like)));
        }

        $titel = WikiSeite::pluck('titel', 'id');
        $eltern = WikiSeite::pluck('parent_id', 'id');

        return $query->limit(300)->get()
            ->map(function (WikiSeite $seite) use ($woerter, $begriff) {
                return [$this->punkte($seite, $woerter, $begriff), $seite];
            })
            ->sort(fn ($a, $b) => [$b[0], $a[1]->titel] <=> [$a[0], $b[1]->titel])
            ->take($limit)
            ->map(fn ($eintrag) => [
                'id' => $eintrag[1]->id,
                'titel' => $eintrag[1]->titel,
                'url' => $eintrag[1]->url(),
                'pfad' => $this->pfadText($eintrag[1], $titel, $eltern),
                'snippet' => $this->snippet((string) $eintrag[1]->inhalt_text, $woerter),
                'tags' => $eintrag[1]->tags->pluck('name')->all(),
                'geaendert' => $eintrag[1]->bearbeitet_at?->format('d.m.Y'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return string[]
     */
    public function woerter(string $begriff): array
    {
        return collect(preg_split('/\s+/u', trim($begriff)))
            ->map(fn ($w) => trim($w, " \t\"'.,;:!?()"))
            ->filter(fn ($w) => mb_strlen($w) >= 2)
            ->unique(fn ($w) => mb_strtolower($w))
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Ausschnitt um den ersten Treffer, HTML-escaped, Treffer mit <mark>.
     *
     * @param  string[]  $woerter
     */
    public function snippet(string $text, array $woerter, int $laenge = 220): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return '';
        }

        $pos = null;
        foreach ($woerter as $wort) {
            $p = mb_stripos($text, $wort);
            if ($p !== false && ($pos === null || $p < $pos)) {
                $pos = $p;
            }
        }

        $start = $pos === null ? 0 : max(0, $pos - 70);
        $ausschnitt = mb_substr($text, $start, $laenge);
        $ausschnitt = ($start > 0 ? '… ' : '').$ausschnitt.($start + $laenge < mb_strlen($text) ? ' …' : '');

        // Erst markieren, dann escapen – so trifft die Hervorhebung nie Entities oder das <mark> selbst
        $muster = '/('.implode('|', array_map(fn ($w) => preg_quote($w, '/'), $woerter)).')/iu';
        $teile = preg_split($muster, $ausschnitt, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$ausschnitt];

        return collect($teile)
            ->map(fn ($teil, $i) => $i % 2 === 1 ? '<mark>'.e($teil).'</mark>' : e($teil))
            ->implode('');
    }

    /**
     * @param  string[]  $woerter
     */
    private function punkte(WikiSeite $seite, array $woerter, string $begriff): int
    {
        $titel = mb_strtolower($seite->titel);
        $ganz = mb_strtolower(trim($begriff));
        $punkte = 0;

        if ($titel === $ganz) {
            $punkte += 200;
        } elseif (str_starts_with($titel, $ganz)) {
            $punkte += 120;
        }

        $tags = $seite->tags->map(fn ($t) => mb_strtolower($t->name));
        $text = mb_strtolower((string) $seite->inhalt_text);
        foreach ($woerter as $wort) {
            $w = mb_strtolower($wort);
            if (str_contains($titel, $w)) {
                $punkte += 50;
            }
            if ($tags->contains(fn ($t) => str_contains($t, $w))) {
                $punkte += 25;
            }
            $punkte += min(10, substr_count($text, $w)) * 2;
        }

        return $punkte;
    }

    private function pfadText(WikiSeite $seite, $titel, $eltern): string
    {
        $teile = [];
        $parent = $eltern[$seite->id] ?? null;
        $tiefe = 0;
        while ($parent && $tiefe++ < 20) {
            if (($titel[$parent] ?? null) !== null) {
                array_unshift($teile, $titel[$parent]);
            }
            $parent = $eltern[$parent] ?? null;
        }

        return Str::limit(implode(' › ', $teile), 120);
    }

    private function escape(string $wort): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $wort);
    }
}
