<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Bereinigt SVG-Code (z. B. Fach-Symbole im Wochenplan) per Whitelist:
 * nur Zeichen-Elemente und Darstellungsattribute, keine Skripte, Event-Handler,
 * externen Verweise oder foreignObject. Ungültiges SVG ergibt einen leeren String.
 */
class SvgSanitizer
{
    private const ELEMENTE = [
        'svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'title', 'desc', 'defs', 'lineargradient', 'radialgradient', 'stop',
    ];

    private const ATTRIBUTE = [
        'xmlns', 'viewbox', 'width', 'height', 'preserveaspectratio', 'id', 'class',
        'd', 'fill', 'fill-rule', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-linecap',
        'stroke-linejoin', 'stroke-dasharray', 'stroke-opacity', 'opacity', 'transform',
        'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'dx', 'dy', 'points',
        'font-size', 'font-family', 'font-weight', 'text-anchor', 'dominant-baseline',
        'offset', 'stop-color', 'stop-opacity', 'gradientunits', 'gradienttransform', 'clip-rule',
    ];

    public static function clean(?string $svg): string
    {
        if ($svg === null || trim($svg) === '' || mb_strlen($svg) > 20000) {
            return '';
        }

        $dom = new DOMDocument();
        $alt = libxml_use_internal_errors(true);
        // Keine externen Entitäten/DTDs laden
        $ok = $dom->loadXML(trim($svg), LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($alt);

        if (! $ok || ! $dom->documentElement || strtolower($dom->documentElement->localName) !== 'svg') {
            return '';
        }
        if ($dom->doctype) {
            $dom->removeChild($dom->doctype);
        }

        self::reinigen($dom->documentElement);
        $dom->documentElement->setAttribute('xmlns', 'http://www.w3.org/2000/svg');

        return (string) $dom->saveXML($dom->documentElement);
    }

    private static function reinigen(DOMNode $knoten): void
    {
        foreach (iterator_to_array($knoten->childNodes) as $kind) {
            if ($kind instanceof DOMElement) {
                if (! in_array(strtolower($kind->localName), self::ELEMENTE, true)) {
                    $knoten->removeChild($kind);

                    continue;
                }
                self::reinigen($kind);
            } elseif ($kind->nodeType === XML_COMMENT_NODE || $kind->nodeType === XML_PI_NODE
                || $kind->nodeType === XML_CDATA_SECTION_NODE) {
                $knoten->removeChild($kind);
            }
        }

        if ($knoten instanceof DOMElement) {
            foreach (iterator_to_array($knoten->attributes) as $attribut) {
                $name = strtolower($attribut->nodeName);
                $wert = (string) $attribut->nodeValue;
                $gefaehrlich = preg_match('/javascript:|data:|expression\s*\(|url\s*\(\s*[\'"]?\s*(?!#)/i', $wert);
                if (! in_array($name, self::ATTRIBUTE, true) || $gefaehrlich) {
                    $knoten->removeAttributeNode($attribut);
                }
            }
        }
    }
}
