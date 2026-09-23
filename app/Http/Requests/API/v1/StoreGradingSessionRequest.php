<?php

namespace App\Http\Requests\API\v1;

class StoreGradingSessionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'schueler_id' => ['required', 'integer', $this->existingSchueler()],
        ];
    }
}
