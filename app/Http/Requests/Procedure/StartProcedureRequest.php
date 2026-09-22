<?php

namespace App\Http\Requests\Procedure;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validierung beim Starten eines Prozesses aus einer Vorlage (§4.1 B-11).
 */
class StartProcedureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) optional($this->user())->can('manage procedures');
    }

    public function rules(): array
    {
        return [
            'name'       => 'required|string|max:120',
            'started_at' => 'nullable|date',
            'description'=> 'nullable|string|max:5000',
        ];
    }
}

