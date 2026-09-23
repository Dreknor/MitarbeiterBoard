<?php

namespace App\Http\Requests\API\v1;

class IndexGradingSessionsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'in:open,completed'],
            'type' => ['sometimes', 'in:group,individual'],
            'mine' => ['sometimes', self::BOOLEAN_QUERY],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
