<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class GroupRequest extends FormRequest
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
        if (auth()->user()->can('edit groups')) {
            return [
                'name' => 'required|alpha_dash|unique:groups,name,' . $this->group['id'],
                'enddate'   => 'nullable|before_or_equal:'.Carbon::now()->addYear()->format('Y-m-d').'|after:'.Carbon::now()->format('Y-m-d'),
                'homegroup' => 'required_with:enddate|exists:groups,id',
                'protected' => 'sometimes',
                'InvationDays' => ['nullable', 'integer', 'min:1']

            ];
        } else {
            return [
                'name' => 'required|alpha_dash|unique:groups,name',
                'homegroup' => 'required|exists:groups,id',
                'enddate'   => 'required|before_or_equal:'.Carbon::now()->addYear()->format('Y-m-d').'|after:'.Carbon::now()->format('Y-m-d'),
            ];
        }
    }
}
