<?php

namespace App\Http\Requests\API\v1;

use App\Services\Api\ApiTokenService;
use Illuminate\Validation\Rule;

class IssueTokenRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
            // Ziel-App des Tokens (Standard: Pädagogen-App)
            'app' => ['nullable', 'string', Rule::in(ApiTokenService::APPS)],
        ];
    }
}
