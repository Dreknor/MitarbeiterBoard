<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateHolidayClaimRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit employe');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'group_id' => [
                'required', 'exists:groups,id',
            ],
            'holiday_claim' => [
                'required', 'integer', 'min:1',
            ],
            'date_start' => [
                'required', 'date',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'group_id.required' => 'Bitte wählen Sie eine Gruppe aus.',
            'group_id.exists' => 'Die ausgewählte Gruppe existiert nicht.',
            'holiday_claim.required' => 'Bitte geben Sie einen Urlaubsanspruch an.',
            'holiday_claim.integer' => 'Der Urlaubsanspruch muss eine ganze Zahl sein.',
            'holiday_claim.min' => 'Der Urlaubsanspruch muss mindestens 1 Tag betragen.',
            'date_start.required' => 'Bitte geben Sie ein Startdatum an.',
            'date_start.date' => 'Das Startdatum muss ein gültiges Datum sein.',
        ];
    }
}
