<?php

namespace Database\Factories\personal;

use App\Models\personal\OrgPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrgPositionFactory extends Factory
{
    protected $model = OrgPosition::class;

    public function definition(): array
    {
        return [
            'name'               => $this->faker->jobTitle(),
            'department_id'      => null,
            'parent_position_id' => null,
            'level'              => 0,
            'is_leadership'      => false,
            'sort_order'         => $this->faker->numberBetween(0, 100),
            'color'              => null,
        ];
    }
}

