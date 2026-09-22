<?php

namespace App\Http\Requests\Procedure;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProcedureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) optional($this->user())->can('manage procedures');
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:5000',
        ];
    }
}

