<?php

namespace App\Http\Requests\API\v1;

use Illuminate\Validation\Rule;

class StartSsoRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'redirect_uri' => ['required', 'string', 'max:255', Rule::in(config('paed_app.redirect_uris', []))],
            // PKCE (RFC 7636): BASE64URL(SHA256(code_verifier)) = 43 Zeichen
            'code_challenge' => ['required', 'string', 'regex:/^[A-Za-z0-9\-_]{43,128}$/'],
            'code_challenge_method' => ['required', 'in:S256'],
            'state' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'redirect_uri.in' => 'Die redirect_uri ist nicht zugelassen.',
            'code_challenge_method.in' => 'Nur S256 wird unterstützt.',
        ];
    }
}
