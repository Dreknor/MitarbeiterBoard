<?php

namespace App\Notifications;

/**
 * Störung oder Behebung im Monitoring (Systemstatus). Eine Benachrichtigung je Prüflauf,
 * die alle Wechsel zusammenfasst.
 */
class SystemstatusGeaendert extends Benachrichtigung
{
    /**
     * @param  array<int, array{name: string, meldung: ?string}>  $stoerungen
     * @param  array<int, array{name: string, meldung: ?string}>  $behoben
     */
    public function __construct(
        public array $stoerungen,
        public array $behoben,
    ) {
    }

    public function kategorie(): string
    {
        return 'system';
    }

    public function titel(object $notifiable): string
    {
        if (count($this->stoerungen) === 1 && $this->behoben === []) {
            return 'Störung: '.$this->stoerungen[0]['name'];
        }
        if ($this->stoerungen === [] && count($this->behoben) === 1) {
            return 'Wieder in Betrieb: '.$this->behoben[0]['name'];
        }

        return $this->stoerungen !== [] ? 'Systemstatus: Störungen' : 'Systemstatus: wieder in Betrieb';
    }

    public function text(object $notifiable): string
    {
        $teile = [];

        if ($this->stoerungen !== []) {
            $teile[] = 'Gestört: '.collect($this->stoerungen)
                ->map(fn ($d) => $d['name'].($d['meldung'] ? ' ('.$d['meldung'].')' : ''))
                ->implode(', ');
        }
        if ($this->behoben !== []) {
            $teile[] = 'Wieder in Betrieb: '.collect($this->behoben)->pluck('name')->implode(', ');
        }

        return implode(' · ', $teile);
    }

    public function zeilen(object $notifiable): array
    {
        $zeilen = [];

        foreach ($this->stoerungen as $dienst) {
            $zeilen[] = '❌ '.$dienst['name'].' ist gestört'.($dienst['meldung'] ? ': '.$dienst['meldung'] : '.');
        }
        foreach ($this->behoben as $dienst) {
            $zeilen[] = '✅ '.$dienst['name'].' ist wieder in Betrieb.';
        }

        return $zeilen;
    }

    public function url(object $notifiable): ?string
    {
        return url('/');
    }

    public function zusatzdaten(object $notifiable): array
    {
        return [
            'stoerungen' => collect($this->stoerungen)->pluck('name')->all(),
            'behoben' => collect($this->behoben)->pluck('name')->all(),
        ];
    }
}
