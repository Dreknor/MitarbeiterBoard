<?php

namespace App\Http\Requests\personal;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Request;


class CreateWorkingTimeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('create roster');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'date' => ['required', 'date'],
            'start' => ['nullable', 'date_format:H:i'],
            'end' => ['nullable', 'date_format:H:i', function ($attribute, $value, $fail) {
                $start = Request::input('start');
                if ($start != null and !Carbon::createFromFormat('H:i', $start)->lessThan(Carbon::createFromFormat('H:i', $value))) {
                    return $fail('Arbeitsende muss nach dem Start liegen');
                }

            }],
            'employe_id' => ['required', 'exists:users,id'],
            'roster_id' => ['required', 'exists:rosters,id'],
            'function' => ['nullable', 'string'],
        ];
    }
}
