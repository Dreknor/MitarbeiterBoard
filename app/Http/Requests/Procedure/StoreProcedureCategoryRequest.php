<?php

namespace App\Http\Requests\Procedure;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcedureCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return (bool) ($u && ($u->can('manage procedures') || $u->can('manage procedure categories')));
    }

    public function rules(): array
    {
        return [
            'name'  => 'required|string|max:120|unique:procedure_categories,name',
            'color' => 'nullable|string|max:16',
        ];
    }
}

