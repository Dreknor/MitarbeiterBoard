<?php

namespace Database\Factories\personal;

use App\Models\personal\Employment;
use App\Models\personal\SchoolType;
use App\Models\personal\TeacherDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherDetailFactory extends Factory
{
    protected $model = TeacherDetail::class;

    public function definition(): array
    {
        return [
            'employment_id'      => Employment::factory(),
            'school_type_id'     => SchoolType::factory(),
            'deputat_hours'      => 28.0,
            'reduction_hours'    => 0.0,
            'reduction_reason'   => null,
            'anrechnungsstunden' => 0.0,
            'valid_from'         => now()->startOfYear(),
            'valid_until'        => null,
        ];
    }
}

