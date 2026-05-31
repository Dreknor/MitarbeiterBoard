<?php

namespace App\Http\Requests\Procedure;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProcedureCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return (bool) ($u && ($u->can('manage procedures') || $u->can('manage procedure categories')));
    }

    public function rules(): array
    {
        $id = $this->route('category')?->id ?? $this->route('category');
        return [
            'name'  => ['required', 'string', 'max:120', Rule::unique('procedure_categories', 'name')->ignore($id)],
            'color' => 'nullable|string|max:16',
        ];
    }
}

