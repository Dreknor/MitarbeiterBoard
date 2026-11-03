<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class storeSecretKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Die Terminal-Sitzung wird im Controller geprüft.
        return true;
    }

    public function rules(): array
    {
        return [
            'secret_key' => ['required', 'digits_between:6,10', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'secret_key.digits_between' => 'Die PIN muss aus 6 bis 10 Ziffern bestehen.',
            'secret_key.confirmed' => 'Die PINs stimmen nicht überein.',
        ];
    }
}
