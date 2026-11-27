<?php

namespace App\View\Composers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AtomFeedComposer
{
    const DEFAULT_FEED_URL = 'https://veranstaltungen.hauptfach-mensch.de/feed/atom.xml';
    const CACHE_TTL        = 15 * 60; // 15 Minuten

    public function compose(View $view): void
    {
        $user = Auth::user();

        $feedUrl = ($user && !empty($user->atom_feed_url))
            ? $user->atom_feed_url
            : self::DEFAULT_FEED_URL;

        $cacheKey = 'atom_feed_' . md5($feedUrl);

        $entries = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($feedUrl) {
            return $this->fetchFeed($feedUrl);
        });

        $view->with('atomFeedEntries', $entries);
        $view->with('atomFeedUrl', $feedUrl);
    }

    protected function fetchFeed(string $url): array
    {
        try {
            $response = Http::timeout(10)->get($url);

            if (!$response->ok()) {
                Log::warning('AtomFeedComposer: Feed konnte nicht geladen werden.', [
                    'url'    => $url,
                    'status' => $response->status(),
                ]);
                return [];
            }

            $xml = $this->parseXml($response->body(), $url);

            if ($xml === null) {
                return [];
            }

            $entries = [];

            // Atom-Format: <entry> Elemente
            if (isset($xml->entry)) {
                foreach ($xml->entry as $entry) {
                    $link = '';
                    if (isset($entry->link)) {
                        foreach ($entry->link as $l) {
                            $attrs = $l->attributes();
                            if (!isset($attrs['rel']) || (string) $attrs['rel'] === 'alternate') {
                                $link = (string) ($attrs['href'] ?? '');
                                break;
                            }
                        }
                    }

                    $published = null;
                    if (isset($entry->published)) {
                        $published = $this->parseDate((string) $entry->published);
                    } elseif (isset($entry->updated)) {
                        $published = $this->parseDate((string) $entry->updated);
                    }

                    $content = '';
                    if (isset($entry->content)) {
                        $content = strip_tags((string) $entry->content);
                    } elseif (isset($entry->summary)) {
                        $content = strip_tags((string) $entry->summary);
                    }

                    $entries[] = [
                        'title'     => (string) ($entry->title ?? ''),
                        'link'      => $link,
                        'published' => $published,
                        'summary'   => mb_substr($content, 0, 200),
                    ];
                }
            }
            // RSS 2.0-Fallback: <item> Elemente
            elseif (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $published = null;
                    if (isset($item->pubDate)) {
                        $published = $this->parseDate((string) $item->pubDate);
                    }

                    $entries[] = [
                        'title'     => (string) ($item->title ?? ''),
                        'link'      => (string) ($item->link ?? ''),
                        'published' => $published,
                        'summary'   => mb_substr(strip_tags((string) ($item->description ?? '')), 0, 200),
                    ];
                }
            }

            return array_slice($entries, 0, 10);
        } catch (\Throwable $e) {
            Log::error('AtomFeedComposer: Fehler beim Laden des Feeds.', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Parst den Feed ohne Warnungen (libxml-Fehler werden gesammelt und geloggt).
     */
    protected function parseXml(string $body, string $url): ?\SimpleXMLElement
    {
        $vorher = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $xml = simplexml_load_string($this->bereinigen($body), 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);

            if ($xml === false) {
                $fehler = libxml_get_errors()[0] ?? null;
                Log::warning('AtomFeedComposer: XML konnte nicht geparst werden.', [
                    'url'   => $url,
                    'error' => $fehler ? trim($fehler->message) . ' (Zeile ' . $fehler->line . ')' : null,
                    'start' => mb_substr($body, 0, 120),
                ]);
                return null;
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($vorher);
        }
    }

    /**
     * Repariert typische Fehler fremder Feeds: BOM/Leerraum am Anfang und
     * Textreste zwischen XML-Deklaration und erstem Element (z. B. "'; ?>"
     * aus einem fehlerhaften Template des Anbieters).
     */
    protected function bereinigen(string $body): string
    {
        $body = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $body));

        if (preg_match('/^(<\?xml[^>]*\?>)([^<]*)(<.*)$/s', $body, $teile) && trim($teile[2]) !== '') {
            return $teile[1] . "\n" . $teile[3];
        }

        return $body;
    }

    protected function parseDate(string $date): ?\Carbon\Carbon
    {
        try {
            return \Carbon\Carbon::parse($date);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

