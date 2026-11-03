<?php

namespace App\Http\Requests\API\v1;

class StudentViewRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'diary_limit' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'include_archived_goals' => ['sometimes', self::BOOLEAN_QUERY],
        ];
    }
}
