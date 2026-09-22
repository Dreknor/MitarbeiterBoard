<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class SnapshotHortPlanungRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage hort planung');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Bitte einen Namen für den Snapshot angeben.',
        ];
    }
}

