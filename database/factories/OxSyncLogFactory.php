<?php

namespace Database\Factories;

use App\Models\OxCalendar;
use App\Models\OxSyncLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class OxSyncLogFactory extends Factory
{
    protected $model = OxSyncLog::class;

    public function definition(): array
    {
        return [
            'ox_calendar_id' => OxCalendar::factory(),
            'aktion'         => $this->faker->randomElement([
                'sync_start', 'sync_complete', 'create', 'update', 'delete', 'error',
            ]),
            'details'        => null,
            'user_id'        => null,
            'ip_adresse'     => null,
        ];
    }

    public function fehler(): static
    {
        return $this->state(fn () => [
            'aktion'  => 'error',
            'details' => ['message' => 'Connection refused', 'code' => 500],
        ]);
    }
}

