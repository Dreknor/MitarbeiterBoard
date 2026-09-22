<?php

namespace App\Http\Requests\Procedure;

use Illuminate\Foundation\Http\FormRequest;

class AssignStepUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) optional($this->user())->can('manage procedures');
    }

    public function rules(): array
    {
        return [
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ];
    }
}

