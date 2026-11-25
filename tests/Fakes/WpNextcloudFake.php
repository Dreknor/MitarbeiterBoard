<?php

namespace Tests\Fakes;

use App\Services\Wochenplan\Nextcloud\WpNextcloudSpeicher;

/** Nextcloud-Ablage im Speicher (Pfade relativ zum Basisordner, Ordner implizit). */
class WpNextcloudFake implements WpNextcloudSpeicher
{
    /** @var array<string, string> Pfad → Inhalt */
    public array $dateien = [];

    public bool $aktiv = true;

    public bool $fehlschlagen = false;

    public function aktiv(): bool
    {
        return $this->aktiv;
    }

    public function ordnerAnlegen(string $pfad): bool
    {
        return ! $this->fehlschlagen;
    }

    public function hochladen(string $inhalt, string $pfad): bool
    {
        if ($this->fehlschlagen) {
            return false;
        }
        $this->dateien[trim($pfad, '/')] = $inhalt;

        return true;
    }

    public function herunterladen(string $pfad): string|false
    {
        return $this->dateien[trim($pfad, '/')] ?? false;
    }

    public function verschieben(string $von, string $nach): bool
    {
        if ($this->fehlschlagen) {
            return false;
        }
        $von = trim($von, '/');
        $nach = trim($nach, '/');
        $gefunden = false;
        foreach ($this->dateien as $pfad => $inhalt) {
            if ($pfad === $von || str_starts_with($pfad, $von.'/')) {
                unset($this->dateien[$pfad]);
                $this->dateien[$nach.substr($pfad, strlen($von))] = $inhalt;
                $gefunden = true;
            }
        }

        return $gefunden;
    }

    public function existiert(string $pfad): bool
    {
        $pfad = trim($pfad, '/');
        foreach (array_keys($this->dateien) as $vorhanden) {
            if ($vorhanden === $pfad || str_starts_with($vorhanden, $pfad.'/')) {
                return true;
            }
        }

        return false;
    }

    public function loeschen(string $pfad): bool
    {
        $pfad = trim($pfad, '/');
        foreach (array_keys($this->dateien) as $vorhanden) {
            if ($vorhanden === $pfad || str_starts_with($vorhanden, $pfad.'/')) {
                unset($this->dateien[$vorhanden]);
            }
        }

        return true;
    }

    public function liste(string $pfad): array
    {
        $pfad = trim($pfad, '/');
        $praefix = $pfad === '' ? '' : $pfad.'/';
        $eintraege = [];
        foreach ($this->dateien as $vorhanden => $inhalt) {
            if ($praefix !== '' && ! str_starts_with($vorhanden, $praefix)) {
                continue;
            }
            $rest = substr($vorhanden, strlen($praefix));
            $teil = explode('/', $rest)[0];
            $ordner = str_contains($rest, '/');
            $eintraege[$teil] = [
                'name' => $teil,
                'pfad' => $praefix.$teil,
                'ordner' => $ordner,
                'groesse' => $ordner ? 0 : strlen($inhalt),
                'mime_type' => $ordner ? null : 'application/pdf',
            ];
        }

        return array_values($eintraege);
    }

    public function webLink(string $pfad, bool $istOrdner = false): ?string
    {
        return 'https://cloud.example/'.ltrim($pfad, '/');
    }

    /** @return string[] */
    public function pfade(): array
    {
        $pfade = array_keys($this->dateien);
        sort($pfade);

        return $pfade;
    }
}
