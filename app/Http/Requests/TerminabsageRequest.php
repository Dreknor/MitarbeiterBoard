<?php

namespace App\Http\Requests;

use App\Model\listen_termine;
use Illuminate\Foundation\Http\FormRequest;

class TerminabsageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $listen_termine =$this->route('listen_termine');
        if (auth()->id() == $listen_termine->reserviert_fuer or auth()->id() == $listen_termine->liste->user_id){
            return true;
        }
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'text' => ['nullable', 'string']
        ];
    }
}
