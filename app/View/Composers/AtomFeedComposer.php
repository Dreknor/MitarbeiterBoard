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

            $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($xml === false) {
                Log::warning('AtomFeedComposer: XML konnte nicht geparst werden.', ['url' => $url]);
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

    protected function parseDate(string $date): ?\Carbon\Carbon
    {
        try {
            return \Carbon\Carbon::parse($date);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

