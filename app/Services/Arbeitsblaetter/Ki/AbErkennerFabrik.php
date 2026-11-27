<?php

namespace App\Services\Arbeitsblaetter\Ki;

/** Wählt den Treiber nach `config('arbeitsblaetter.ki.treiber')`. */
class AbErkennerFabrik
{
    public const TREIBER = [
        'keiner' => 'Keine KI (nur manuelle Erfassung)',
        'anthropic' => 'Anthropic (Claude)',
        'fake' => 'Testtreiber',
    ];

    public static function erstellen(): AbErkenner
    {
        return match (config('arbeitsblaetter.ki.treiber')) {
            'anthropic' => app(AnthropicErkenner::class),
            'fake' => app(FakeErkenner::class),
            default => new KeinErkenner(),
        };
    }
}
