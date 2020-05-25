<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class createThemeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'theme' => 'required|string',
            'goal' => 'required|string',
            'duration' => 'required|integer|min:5|max:240',
            'type'     => 'required|exists:types,id',
            'information' => 'nullable|string',
            'date'  => 'required|date|after:'.Carbon::now()->addDays(config('config.themes.addDays'))->startOfDay()
        ];
    }
}
