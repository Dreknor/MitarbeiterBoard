<?php

namespace Database\Factories\personal;

use App\Models\personal\Timesheet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimesheetFactory extends Factory
{
    protected $model = Timesheet::class;

    public function definition(): array
    {
        return [
            'employe_id'           => User::factory(),
            'month'                => now()->format('m'),
            'year'                 => now()->format('Y'),
            'holidays_old'         => 0,
            'holidays_new'         => 30,
            'holidays_rest'        => 30,
            'working_time_account' => 0,
            'comment'              => null,
            'locked_at'            => null,
            'locked_by'            => null,
        ];
    }

    /** Gesperrtes Stundenzettel */
    public function locked(): static
    {
        return $this->state(fn () => [
            'locked_at' => now(),
            'locked_by' => User::factory(),
        ]);
    }
}

