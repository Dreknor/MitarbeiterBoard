<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class CreateVertretungRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit vertretungen');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'date'=>[
                'required','date','after_or_equal:'.Carbon::today()
            ],
            'klassen_id' => [
                'required','exists:klassen,id'
            ],
            'users_id' => [
                'nullable','exists:users,id'
            ],
            'stunde' => [
                'required','integer','min:0'
            ],
            'Doppelstunde' => [
                'nullable'
            ],
            'comment' => [
                'nullable','string'
            ],
            'altFach' => [
                'nullable','string', 'max:12'
            ],
            'neuFach' => [
                'nullable','string', 'max:12'
            ],
            'type' => [
                'string', 'nullable', 'max:32'
            ]

        ];
    }
}
