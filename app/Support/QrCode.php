<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** QR-Codes als SVG (ohne Imagick), z. B. für Links in Wochenplan-PDFs. */
class QrCode
{
    public static function svg(string $text, int $groesse = 120): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($groesse, 1), new SvgImageBackEnd()));

        return $writer->writeString($text);
    }

    public static function dataUri(string $text, int $groesse = 120): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($text, $groesse));
    }
}
