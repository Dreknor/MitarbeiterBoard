<?php

namespace Database\Factories\Wochenplan;

use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use App\Models\Wochenplan\WpFormatvorlage;
use App\Models\Wochenplan\WpPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class WpPlanFactory extends Factory
{
    protected $model = WpPlan::class;

    public function definition(): array
    {
        return [
            'name'                    => $this->faker->words(3, true),
            'gueltig_von'             => now()->startOfWeek(),
            'gueltig_bis'             => now()->endOfWeek(),
            'klasse_id'               => Klasse::factory(),
            'schueler_id'             => null,
            'parent_plan_id'          => null,
            'vorlage_id'              => null,
            'formatvorlage_id'        => WpFormatvorlage::factory(),
            'selbsteinschaetzung'     => 0,
            'taegliche_uebungen_aktiv'=> false,
            'is_vorlage'              => false,
            'vorlage_name'            => null,
            'created_by'              => User::factory(),
        ];
    }

    /** Plan als Vorlage */
    public function alsVorlage(): static
    {
        return $this->state(fn () => [
            'is_vorlage'   => true,
            'klasse_id'    => null,
            'schueler_id'  => null,
            'vorlage_name' => $this->faker->words(2, true),
        ]);
    }

    /** Klassenplan (Standard: kein Schüler) */
    public function alsKlassenplan(): static
    {
        return $this->state(fn () => [
            'is_vorlage'  => false,
            'schueler_id' => null,
        ]);
    }

    /** Schülerplan (abgeleitet von einem Klassenplan) */
    public function alsSchuelerplan(WpPlan $parentPlan): static
    {
        return $this->state(fn () => [
            'is_vorlage'     => false,
            'klasse_id'      => $parentPlan->klasse_id,
            'schueler_id'    => Schueler::factory()->create(['klasse_id' => $parentPlan->klasse_id])->id,
            'parent_plan_id' => $parentPlan->id,
        ]);
    }
}

