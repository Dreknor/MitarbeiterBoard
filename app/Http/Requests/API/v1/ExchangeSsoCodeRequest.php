<?php

namespace App\Http\Requests\API\v1;

use App\Services\Api\ApiTokenService;
use Illuminate\Validation\Rule;

class ExchangeSsoCodeRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
            // PKCE (RFC 7636): 43–128 Zeichen [A-Z a-z 0-9 - . _ ~]
            'code_verifier' => ['required', 'string', 'regex:/^[A-Za-z0-9\-._~]{43,128}$/'],
            'device_name' => ['required', 'string', 'max:100'],
            // Ziel-App des Tokens (Standard: Pädagogen-App)
            'app' => ['nullable', 'string', Rule::in(ApiTokenService::APPS)],
        ];
    }
}
