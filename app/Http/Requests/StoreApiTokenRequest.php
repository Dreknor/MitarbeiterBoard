<?php

namespace App\Http\Requests;

use App\Services\Api\ApiTokenService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiTokenRequest extends FormRequest
{
    // Eigener Error-Bag, damit Fehler nicht bei anderen Formularen der Profilseite erscheinen
    protected $errorBag = 'apiToken';

    public function authorize(): bool
    {
        return $this->user()?->can('create api tokens') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'laufzeit' => ['required', 'integer', Rule::in(array_keys(ApiTokenService::API_TOKEN_LAUFZEITEN))],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Bezeichnung',
            'laufzeit' => 'Laufzeit',
        ];
    }
}
