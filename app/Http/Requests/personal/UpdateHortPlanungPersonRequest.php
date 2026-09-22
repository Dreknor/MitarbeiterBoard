<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHortPlanungPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage hort planung');
    }

    public function rules(): array
    {
        return [
            'stunden_gesamt' => ['nullable', 'numeric', 'min:0'],
            'stunden_stadt'  => ['nullable', 'numeric', 'min:0'],
            'kommentar'      => ['nullable', 'string', 'max:255'],
        ];
    }
}

