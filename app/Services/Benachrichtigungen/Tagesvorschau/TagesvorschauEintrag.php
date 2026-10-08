<?php

namespace App\Services\Benachrichtigungen\Tagesvorschau;

/**
 * Eine Zeile der Tagesübersicht.
 */
final class TagesvorschauEintrag
{
    public function __construct(
        public readonly string $titel,
        public readonly ?string $zeit = null,
        public readonly ?string $details = null,
        public readonly ?string $url = null,
        public readonly bool $hervorheben = false,
    ) {
    }
}
