<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class updateTimesheetDayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit employe') or ($this->timesheetDay->employe->id == auth()->id() and auth()->user()->can('has timesheet'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'start' => [
                'required', 'date_format:H:i', 'before:end'
            ],
            'end' => [
                'required', 'date_format:H:i', 'after:start'
            ],
            'pause' => [
                'nullable', 'integer', 'min:0'
            ],
            'comment' => [
                'nullable', 'string', 'max:60'
            ]
        ];
    }
}
