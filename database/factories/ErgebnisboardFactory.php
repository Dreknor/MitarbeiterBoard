<?php

namespace Database\Factories;

use App\Models\Ergebnisboard;
use App\Models\User;
use App\Support\ErgebnisboardVorlagen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Ergebnisboard mit dem Raster einer eingebauten Vorlage (Standard: Kanban).
 */
class ErgebnisboardFactory extends Factory
{
    protected $model = Ergebnisboard::class;

    public function definition(): array
    {
        return $this->ausVorlage('kanban') + [
            'titel' => $this->faker->sentence(3),
            'untertitel' => null,
            'farbschema' => 'klar',
            'meeting_id' => null,
            'meeting_lesen' => false,
            'theme_id' => null,
            'version' => 1,
            'created_by' => User::factory(),
            'archiviert_at' => null,
        ];
    }

    /** Raster einer eingebauten Vorlage übernehmen (swot, kanban, umsetzungsstand, frei …). */
    public function vorlage(string $schluessel): static
    {
        return $this->state(fn () => $this->ausVorlage($schluessel));
    }

    public function archiviert(): static
    {
        return $this->state(fn () => ['archiviert_at' => now()]);
    }

    private function ausVorlage(string $schluessel): array
    {
        $vorlage = ErgebnisboardVorlagen::normalisieren(ErgebnisboardVorlagen::VORLAGEN[$schluessel]);

        return [
            'vorlage' => $schluessel,
            'spalten' => $vorlage['spalten'],
            'bereiche' => $vorlage['bereiche'],
            'achsen' => $vorlage['achsen'],
            'legende' => $vorlage['legende'],
            'bereiche_frei' => (bool) $vorlage['bereiche_frei'],
            'auto_status' => (bool) $vorlage['auto_status'],
        ];
    }
}
