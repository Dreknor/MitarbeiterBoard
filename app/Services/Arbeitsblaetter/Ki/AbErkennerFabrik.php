<?php

namespace App\Services\Arbeitsblaetter\Ki;

/** Wählt den Treiber nach `config('arbeitsblaetter.ki.treiber')`. */
class AbErkennerFabrik
{
    public const TREIBER = [
        'keiner' => 'Keine KI (nur manuelle Erfassung)',
        'anthropic' => 'Anthropic (Claude)',
        'gemini' => 'Google Gemini',
        'openrouter_auto' => 'OpenRouter (automatische Modellwahl)',
        'openrouter_free' => 'OpenRouter (kostenlose Modelle)',
        'fake' => 'Testtreiber',
    ];

    public static function erstellen(): AbErkenner
    {
        return match (config('arbeitsblaetter.ki.treiber')) {
            'anthropic' => app(AnthropicErkenner::class),
            'gemini' => app(GeminiErkenner::class),
            'openrouter_auto' => new OpenRouterErkenner('auto'),
            'openrouter_free' => new OpenRouterErkenner('free'),
            'fake' => app(FakeErkenner::class),
            default => new KeinErkenner(),
        };
    }
}
