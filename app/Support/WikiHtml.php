<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * HTML des Wikis (TinyMCE):
 *  - bereinigen(): HTMLPurifier mit Wiki-Whitelist (Hinweisboxen, Bild-Klassen, figure/figcaption) – beim Speichern
 *  - ausgabe():    bereinigtes HTML für {!! !!}, gecacht je Revision/Version (Verteidigung in der Tiefe)
 *  - aufbereiten(): Anzeige-Schritt – Überschriften-IDs + Inhaltsverzeichnis, rote/gesperrte Wiki-Links,
 *                   Tabellen-Scrollcontainer, Lightbox-Markierung für Bilder
 *  - klartext(), interneSlugs(): Suche bzw. Backlinks
 */
class WikiHtml
{
    /** Erhöhen, wenn sich die Whitelist ändert (leert den Ausgabe-Cache). */
    private const STAND = 3;

    public const KLASSEN = [
        'wiki-hinweis', 'wiki-hinweis-info', 'wiki-hinweis-tipp', 'wiki-hinweis-warnung',
        'wiki-bild-klein', 'wiki-bild-mittel', 'wiki-bild-voll', 'wiki-bild-links', 'wiki-bild-rechts',
        'image', 'wiki-datei',
    ];

    private static ?HTMLPurifier $purifier = null;

    public static function bereinigen(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return trim(self::purifier()->purify($html));
    }

    public static function ausgabe(?string $html, string $schluessel): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return Cache::remember('wiki-html-'.self::STAND.'-'.$schluessel.'-'.md5($html), now()->addDays(7), fn () => self::bereinigen($html));
    }

    /**
     * @param  callable(string $slug): string  $linkStatus  'ok' | 'fehlt' | 'gesperrt'
     * @return array{html: string, toc: array<int, array{id: string, text: string, ebene: int}>}
     */
    public static function aufbereiten(string $html, callable $linkStatus): array
    {
        if (trim($html) === '') {
            return ['html' => '', 'toc' => []];
        }

        [$dom, $wurzel] = self::dom($html);
        $xpath = new DOMXPath($dom);

        $toc = [];
        $ids = [];
        foreach ($xpath->query('.//h2|.//h3', $wurzel) as $h) {
            /** @var DOMElement $h */
            $text = trim(preg_replace('/\s+/u', ' ', $h->textContent));
            if ($text === '') {
                continue;
            }
            $basis = Str::slug($text, '-', 'de') ?: 'abschnitt';
            $id = $basis;
            $n = 2;
            while (isset($ids[$id])) {
                $id = $basis.'-'.$n++;
            }
            $ids[$id] = true;
            $h->setAttribute('id', $id);
            $h->setAttribute('class', 'wiki-ueberschrift');
            $anker = $dom->createElement('a', '#');
            $anker->setAttribute('href', '#'.$id);
            $anker->setAttribute('class', 'wiki-anker');
            $anker->setAttribute('aria-label', 'Link zu diesem Abschnitt kopieren');
            $anker->setAttribute('data-wiki-anker', '');
            $h->appendChild($anker);
            $toc[] = ['id' => $id, 'text' => $text, 'ebene' => $h->nodeName === 'h2' ? 2 : 3];
        }

        foreach (iterator_to_array($xpath->query('.//a[@href]', $wurzel)) as $a) {
            /** @var DOMElement $a */
            $slug = self::wikiSlugAusHref($a->getAttribute('href'));
            if ($slug === null) {
                if (preg_match('#^https?://#i', $a->getAttribute('href'))) {
                    $a->setAttribute('target', '_blank');
                    $a->setAttribute('rel', 'noopener');
                    $a->setAttribute('class', trim($a->getAttribute('class').' wiki-link-extern'));
                }
                continue;
            }
            $status = $linkStatus($slug);
            if ($status === 'fehlt') {
                $a->setAttribute('class', trim($a->getAttribute('class').' wiki-link-rot'));
                $a->setAttribute('href', route('wiki.create', ['titel' => Str::of($slug)->replace('-', ' ')->ucfirst()->toString()]));
                $a->setAttribute('title', 'Seite existiert noch nicht – anlegen');
            } elseif ($status === 'gesperrt') {
                $span = $dom->createElement('span', 'eingeschränkte Seite');
                $span->setAttribute('class', 'wiki-link-gesperrt');
                $a->parentNode->replaceChild($span, $a);
            }
        }

        foreach (iterator_to_array($xpath->query('.//table', $wurzel)) as $tabelle) {
            $huelle = $dom->createElement('div');
            $huelle->setAttribute('class', 'wiki-tabelle');
            $tabelle->parentNode->replaceChild($huelle, $tabelle);
            $huelle->appendChild($tabelle);
        }

        foreach ($xpath->query('.//img', $wurzel) as $img) {
            /** @var DOMElement $img */
            $img->setAttribute('loading', 'lazy');
            $img->setAttribute('data-wiki-lightbox', '');
        }

        return ['html' => self::innerHtml($dom, $wurzel), 'toc' => $toc];
    }

    public static function klartext(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }
        $text = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr|/blockquote|/pre|/figcaption|/td|/th)[^>]*>#i', "$0\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace("/ *\n */", "\n", $text);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * Slugs aller internen Wiki-Links im HTML.
     *
     * @return string[]
     */
    public static function interneSlugs(?string $html): array
    {
        if ($html === null || ! preg_match_all('/href\s*=\s*"([^"]*)"/i', $html, $treffer)) {
            return [];
        }

        return collect($treffer[1])
            ->map(fn ($href) => self::wikiSlugAusHref(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Liefert den Slug, wenn der Link auf eine Wiki-Seite dieser Installation zeigt (sonst null). */
    public static function wikiSlugAusHref(string $href): ?string
    {
        $teile = parse_url(trim($href));
        if ($teile === false || isset($teile['fragment']) && ! isset($teile['path'])) {
            return null;
        }
        if (isset($teile['host']) && strcasecmp($teile['host'], (string) parse_url(config('app.url'), PHP_URL_HOST)) !== 0) {
            return null;
        }
        $basis = rtrim((string) parse_url(url('/wiki'), PHP_URL_PATH), '/') ?: '/wiki';
        $pfad = $teile['path'] ?? '';
        if (! str_starts_with($pfad, $basis.'/')) {
            return null;
        }
        $rest = explode('/', substr($pfad, strlen($basis) + 1));
        $slug = rawurldecode($rest[0] ?? '');
        if ($slug === '' || count($rest) > 1 || in_array(mb_strtolower($slug), \App\Services\Wiki\WikiService::RESERVIERT, true)) {
            return null;
        }

        return $slug;
    }

    /**
     * @return array{0: DOMDocument, 1: DOMElement}
     */
    public static function dom(string $html): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><html><body><div id="wiki-wurzel">'.$html.'</div></body></html>', LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        return [$dom, $dom->getElementById('wiki-wurzel')];
    }

    public static function innerHtml(DOMDocument $dom, DOMElement $wurzel): string
    {
        $html = '';
        foreach ($wurzel->childNodes as $kind) {
            $html .= $dom->saveHTML($kind);
        }

        return $html;
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.DefinitionID', 'wiki');
        $config->set('HTML.DefinitionRev', self::STAND);
        $config->set('HTML.Allowed', implode(',', [
            'p[class|style]', 'br', 'hr', 'div[class]', 'span[style]', 'blockquote', 'pre', 'code',
            'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'sub', 'sup', 'mark',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol[start]', 'li',
            'a[href|title|target|class]',
            'img[src|alt|width|height|class]',
            'figure[class]', 'figcaption',
            'table[border|cellpadding|cellspacing|class]', 'caption', 'thead', 'tbody', 'tfoot', 'tr',
            'th[colspan|rowspan|scope]', 'td[colspan|rowspan]',
        ]));
        $config->set('Attr.AllowedClasses', self::KLASSEN);
        $config->set('CSS.AllowedProperties', ['text-align', 'text-decoration', 'font-weight', 'font-style']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('HTML.TargetNoopener', true);
        $config->set('AutoFormat.RemoveEmpty', false);

        $cacheDir = storage_path('framework/cache/htmlpurifier');
        if (is_dir($cacheDir) || @mkdir($cacheDir, 0775, true)) {
            $config->set('Cache.SerializerPath', $cacheDir);
        } else {
            $config->set('Cache.DefinitionImpl', null);
        }

        if ($def = $config->maybeGetRawHTMLDefinition()) {
            $def->addElement('figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common');
            $def->addElement('figcaption', 'Inline', 'Flow', 'Common');
            $def->addElement('mark', 'Inline', 'Inline', 'Common');
        }

        return self::$purifier = new HTMLPurifier($config);
    }
}
