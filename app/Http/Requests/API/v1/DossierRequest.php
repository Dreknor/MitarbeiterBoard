<?php

namespace App\Http\Requests\API\v1;

class DossierRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'from_date' => ['sometimes', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'include_confidential' => ['sometimes', self::BOOLEAN_QUERY],
        ];
    }
}
