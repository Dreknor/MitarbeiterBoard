<?php

namespace App\Http\Requests\API\v1;

use App\Models\GradingDocumentationSession;
use Illuminate\Validation\Rule;

class UpdateGradingSessionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'answer_order_mode' => ['required', Rule::in(GradingDocumentationSession::ANSWER_ORDER_MODES)],
        ];
    }
}
