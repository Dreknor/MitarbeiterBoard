<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Bereinigt HTML aus dem WYSIWYG-Editor (TinyMCE), bevor es mit {!! !!}
 * ausgegeben wird. Erlaubt übliche Formatierung, Listen, Tabellen und Links,
 * entfernt Skripte, Event-Handler und gefährliche URLs.
 */
class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return self::purifier()->purify($html);
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', implode(',', [
            'p', 'br', 'hr', 'div', 'span[style]', 'blockquote', 'pre', 'code',
            'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'sub', 'sup',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li',
            'a[href|title|target]',
            'img[src|alt|width|height]',
            'table[border|cellpadding|cellspacing]', 'thead', 'tbody', 'tfoot', 'tr', 'th[colspan|rowspan]', 'td[colspan|rowspan]',
        ]));
        $config->set('CSS.AllowedProperties', ['color', 'background-color', 'text-align', 'text-decoration', 'font-weight', 'font-style']);
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

        return self::$purifier = new HTMLPurifier($config);
    }
}
