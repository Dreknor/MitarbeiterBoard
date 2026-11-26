<?php

use App\Models\WikiSeite;
use App\Services\Wiki\WikiAnhangService;
use App\Services\Wiki\WikiService;
use App\Services\Wiki\WikiZugriff;
use App\Support\WikiHtml;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Übernimmt das alte Wiki (wiki_sites, eine Zeile je Fassung) in wiki_seiten/wiki_revisionen.
 *
 * - Jede (nicht gelöschte) alte Fassung wird zu einer Revision mit Autor und Datum, die neueste zum aktuellen Inhalt.
 * - Präfixe im Titel („MitarbeiterBoard: Abos“) werden zu Elternseiten (ab zwei Seiten bzw. wenn es eine Seite mit
 *   dem Präfix als Titel gibt), „OX“ + „Synchronisation …“ → „OX & Kalender“, „Drucker…“ → „Drucker & Geräte“,
 *   die Handbücher der Module (Systemseiten) unter „MitarbeiterBoard“.
 * - base64-Bilder im Text werden Anhänge; Anhänge aller Fassungen werden je Seite zusammengeführt (gleicher Name +
 *   Größe) und auf die private Disk `wiki` verschoben, bisherige Galerie-Bilder landen in der Dateiliste.
 * - Alte URLs (/wiki/Alter-Titel) leiten weiter, interne Links und /image/{id}-Links werden umgeschrieben.
 * - wiki_sites bleibt als Sicherung unverändert stehen.
 */
return new class extends Migration
{
    private const ALT_MODEL = 'App\\Models\\WikiSite';

    /** Handbücher der Module (bisher über Migrationen eingespielt) → Systemseiten mit festem Slug. */
    private const SYSTEMSEITEN = [
        'Pädagogisches Tagebuch — Handbuch' => 'paedagogisches-tagebuch-handbuch',
        'Raumplanungstool — Anleitung' => 'raumplanungstool-anleitung',
        'Prozesse & Prozeduren — Anleitung' => 'prozesse-prozeduren-anleitung',
        'Anleitung Wochenplansystem' => 'anleitung-wochenplansystem',
        'Kalender' => 'kalender',
        'Benachrichtigungen' => 'benachrichtigungen',
    ];

    private const ELTERN_SYSTEM = 'MitarbeiterBoard';

    public function up(): void
    {
        if (! Schema::hasTable('wiki_sites') || DB::table('wiki_seiten')->exists()) {
            return;
        }

        $zeilen = DB::table('wiki_sites')->whereNull('deleted_at')->orderBy('created_at')->orderBy('id')->get();
        if ($zeilen->isEmpty()) {
            return;
        }

        $service = app(WikiService::class);
        $anhaenge = app(WikiAnhangService::class);
        $userIds = DB::table('users')->pluck('id')->flip();

        $gruppen = $zeilen->groupBy(fn ($z) => $this->norm($z->title));
        $einordnung = $this->einordnen($gruppen->keys());

        // 1) Elternseiten, die es noch nicht als eigene Seite gibt
        $seitenNachTitel = [];
        foreach (collect($einordnung)->pluck('eltern')->filter()->unique() as $eltern) {
            if (! $gruppen->has($eltern)) {
                $seite = new WikiSeite(['titel' => $eltern]);
                $seite->slug = $service->slugFuer($eltern);
                $seite->inhalt = '<p>Übersicht der Seiten zum Thema „'.e($eltern).'“.</p>';
                $seite->inhalt_text = WikiHtml::klartext($seite->inhalt);
                $seite->version = 1;
                $seite->bearbeitet_at = now();
                $seite->save();
                DB::table('wiki_revisionen')->insert([
                    'seite_id' => $seite->id, 'titel' => $seite->titel, 'inhalt' => $seite->inhalt,
                    'notiz' => 'Bei der Umstellung des Wikis angelegt', 'user_id' => null, 'created_at' => now(),
                ]);
                $seitenNachTitel[$eltern] = $seite;
            }
        }

        // 2) Seiten aus den alten Fassungen (Elternseiten zuerst, damit Slugs der Kinder ggf. das Präfix bekommen)
        $elternTitel = collect($einordnung)->pluck('eltern')->filter();
        $reihenfolge = $gruppen->keys()
            ->sortBy(fn ($t) => match (true) {
                $elternTitel->contains($t) => 0,
                isset(self::SYSTEMSEITEN[$t]), $t === 'Start' => 1,
                default => 2,
            })
            ->values();
        $alteSlugs = [];
        $medienZuordnung = [];

        foreach ($reihenfolge as $alterTitel) {
            /** @var Collection $fassungen */
            $fassungen = $gruppen[$alterTitel];
            $neu = $einordnung[$alterTitel];
            $erste = $fassungen->first();
            $letzte = $fassungen->last();
            $autor = fn ($z) => $z->author_id && isset($userIds[$z->author_id]) ? (int) $z->author_id : null;

            $seite = new WikiSeite(['titel' => $neu['titel']]);
            if ($alterTitel === 'Start') {
                $seite->slug = WikiSeite::START_SLUG;
            } elseif (isset(self::SYSTEMSEITEN[$alterTitel]) && $this->slugFrei(self::SYSTEMSEITEN[$alterTitel])) {
                $seite->slug = self::SYSTEMSEITEN[$alterTitel];
                $seite->systemseite = true;
            } else {
                $seite->slug = $service->slugFuer($neu['titel'], $neu['eltern'] ? ($seitenNachTitel[$neu['eltern']] ?? null)?->id : null);
            }
            $seite->inhalt = '';
            $seite->version = 1;
            $seite->erstellt_von = $autor($erste);
            $seite->geaendert_von = $autor($letzte);
            $seite->bearbeitet_at = $letzte->updated_at ?? $letzte->created_at;
            $seite->created_at = $erste->created_at ?? now();
            $seite->save();
            $seitenNachTitel[$alterTitel] = $seite;

            foreach ($fassungen->pluck('title')->unique() as $t) {
                $alteSlugs[str_replace(' ', '-', $t)] = $seite;
            }

            // Anhänge aller Fassungen zusammenführen und auf die Wiki-Disk verschieben
            $bekannt = [];
            $medien = Media::where('model_type', self::ALT_MODEL)->whereIn('model_id', $fassungen->pluck('id'))->orderBy('id')->get();
            foreach ($medien as $media) {
                $schluessel = $media->file_name.'|'.$media->size;
                if (isset($bekannt[$schluessel])) {
                    $medienZuordnung[$media->id] = $bekannt[$schluessel];
                    continue;
                }
                try {
                    if (! is_file($media->getPath())) {
                        continue;
                    }
                    $neuesMedium = $media->copy($seite, WikiSeite::DATEIEN, 'wiki');
                    $bekannt[$schluessel] = $neuesMedium->id;
                    $medienZuordnung[$media->id] = $neuesMedium->id;
                } catch (\Throwable $e) {
                    Log::warning('Wiki-Umstellung: Anhang nicht übernommen', ['media' => $media->id, 'fehler' => $e->getMessage()]);
                }
            }

            // Fassungen → Revisionen
            $inhalt = '';
            foreach ($fassungen as $z) {
                $inhalt = WikiHtml::bereinigen($anhaenge->base64Umwandeln($seite, (string) $z->text));
                DB::table('wiki_revisionen')->insert([
                    'seite_id' => $seite->id,
                    'titel' => $neu['titel'],
                    'inhalt' => $inhalt,
                    'notiz' => null,
                    'user_id' => $autor($z),
                    'created_at' => $z->updated_at ?? $z->created_at ?? now(),
                ]);
            }
            $seite->inhalt = $inhalt;
            $seite->inhalt_text = WikiHtml::klartext($inhalt);
            $seite->save();
        }

        // 3) Baum setzen
        foreach ($einordnung as $alterTitel => $neu) {
            if ($neu['eltern'] && isset($seitenNachTitel[$neu['eltern']], $seitenNachTitel[$alterTitel])
                && $seitenNachTitel[$neu['eltern']]->id !== $seitenNachTitel[$alterTitel]->id) {
                $seitenNachTitel[$alterTitel]->update(['parent_id' => $seitenNachTitel[$neu['eltern']]->id]);
            }
        }

        // 4) Weiterleitungen der alten URLs
        foreach ($alteSlugs as $alt => $seite) {
            if ($alt !== '' && mb_strtolower($alt) !== $seite->slug && ! DB::table('wiki_weiterleitungen')->where('alter_slug', $alt)->exists()) {
                DB::table('wiki_weiterleitungen')->insert([
                    'alter_slug' => Str::limit($alt, 190, ''), 'seite_id' => $seite->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // 5) Links umschreiben (alte Titel-URLs → neue Slugs, /image/{id} → /wiki/anhang/{id}), Backlinks aufbauen
        $umschreiben = fn (?string $html) => $this->linksUmschreiben((string) $html, $alteSlugs, $medienZuordnung);
        DB::table('wiki_revisionen')->orderBy('id')->chunkById(100, function ($revisionen) use ($umschreiben) {
            foreach ($revisionen as $r) {
                $neu = $umschreiben($r->inhalt);
                if ($neu !== $r->inhalt) {
                    DB::table('wiki_revisionen')->where('id', $r->id)->update(['inhalt' => $neu]);
                }
            }
        });
        foreach (WikiSeite::all() as $seite) {
            $neu = $umschreiben($seite->inhalt);
            if ($neu !== $seite->inhalt) {
                $seite->inhalt = $neu;
                $seite->inhalt_text = WikiHtml::klartext($neu);
                $seite->save();
            }
            $service->linksAktualisieren($seite);
        }

        WikiZugriff::zuruecksetzen();
    }

    public function down(): void
    {
        // Neue Tabellen werden in der vorherigen Migration entfernt; wiki_sites ist unverändert.
        DB::table('wiki_links')->delete();
        DB::table('wiki_weiterleitungen')->delete();
        DB::table('wiki_revisionen')->delete();
        foreach (WikiSeite::withTrashed()->get() as $seite) {
            $seite->clearMediaCollection(WikiSeite::BILDER);
            $seite->clearMediaCollection(WikiSeite::DATEIEN);
        }
        DB::table('wiki_seiten')->update(['parent_id' => null]);
        DB::table('wiki_seiten')->delete();
    }

    /**
     * @return array<string, array{titel: string, eltern: ?string}>
     */
    private function einordnen(Collection $titel): array
    {
        $praefix = fn (string $t) => preg_match('/^([^:]{2,40}):\s*(.+)$/u', $t, $m) ? [trim($m[1]), trim($m[2])] : null;

        $anzahl = [];
        foreach ($titel as $t) {
            if ($p = $praefix($t)) {
                $anzahl[$p[0]] = ($anzahl[$p[0]] ?? 0) + 1;
            }
        }
        foreach ($anzahl as $p => $n) {
            if ($titel->contains($p)) {
                $anzahl[$p] = $n + 1;
            }
        }

        $drucker = $titel->filter(fn ($t) => preg_match('/^drucker/iu', $t))->count() >= 2;
        $ox = ($anzahl['OX'] ?? 0) + $titel->filter(fn ($t) => preg_match('/^synchronisation\b/iu', $t))->count() >= 2;

        $ergebnis = [];
        foreach ($titel as $t) {
            $neu = ['titel' => $t, 'eltern' => null];
            $p = $praefix($t);

            if ($drucker && preg_match('/^drucker/iu', $t)) {
                $neu = ['titel' => $this->gross(preg_replace('/^drucker[\s_:\-]+/iu', '', $t)), 'eltern' => 'Drucker & Geräte'];
            } elseif ($ox && ($p && $p[0] === 'OX')) {
                $neu = ['titel' => $this->gross($p[1]), 'eltern' => 'OX & Kalender'];
            } elseif ($ox && preg_match('/^synchronisation\b/iu', $t)) {
                $neu['eltern'] = 'OX & Kalender';
            } elseif ($p && ($anzahl[$p[0]] ?? 0) >= 2) {
                $neu = ['titel' => $this->gross($p[1]), 'eltern' => $p[0]];
            } elseif (isset(self::SYSTEMSEITEN[$t]) && (($anzahl[self::ELTERN_SYSTEM] ?? 0) >= 2 || $titel->contains(self::ELTERN_SYSTEM))) {
                $neu['eltern'] = self::ELTERN_SYSTEM;
            }

            if ($neu['eltern'] === $t) {
                $neu['eltern'] = null;
            }
            $ergebnis[$t] = $neu;
        }

        return $ergebnis;
    }

    private function linksUmschreiben(string $html, array $alteSlugs, array $medien): string
    {
        if ($html === '') {
            return $html;
        }
        $host = (string) parse_url(config('app.url'), PHP_URL_HOST);
        $basis = rtrim((string) parse_url(url('/wiki'), PHP_URL_PATH), '/') ?: '/wiki';

        return preg_replace_callback('/(href|src)="([^"]*)"/i', function ($m) use ($alteSlugs, $medien, $host, $basis) {
            $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $teile = parse_url($url);
            if ($teile === false || (isset($teile['host']) && strcasecmp($teile['host'], $host) !== 0)) {
                return $m[0];
            }
            $pfad = $teile['path'] ?? '';

            if (preg_match('#/image/(\d+)$#', $pfad, $bild) && isset($medien[(int) $bild[1]])) {
                return $m[1].'="'.route('wiki.anhang', $medien[(int) $bild[1]], false).'"';
            }

            if (str_starts_with($pfad, $basis.'/')) {
                $segmente = explode('/', substr($pfad, strlen($basis) + 1));
                $alt = rawurldecode($segmente[0] ?? '');
                $seite = $alteSlugs[$alt] ?? $alteSlugs[str_replace(' ', '-', $alt)] ?? null;
                if ($seite) {
                    $ziel = $seite->slug === WikiSeite::START_SLUG ? $basis : $basis.'/'.$seite->slug;

                    return $m[1].'="'.e($ziel.(isset($teile['fragment']) ? '#'.$teile['fragment'] : '')).'"';
                }
            }

            return $m[0];
        }, $html) ?? $html;
    }

    private function slugFrei(string $slug): bool
    {
        return ! DB::table('wiki_seiten')->where('slug', $slug)->exists();
    }

    private function norm(string $titel): string
    {
        return trim(preg_replace('/\s+/u', ' ', $titel));
    }

    private function gross(string $text): string
    {
        $text = $this->norm($text);

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }
};
