<?php
namespace App\Http\Requests\Wochenplan;
use Illuminate\Foundation\Http\FormRequest;
class WpAufgabeRequest extends FormRequest {
    public function authorize(): bool { return auth()->user()->canAny(["create wochenplan", "create Wochenplan"]); }
    public function rules(): array {
        return [
            "aufgabe"    => ["required", "string"],
            "dauer"      => ["nullable", "string", "max:50"],
            "sort_order" => ["sometimes", "integer", "min:0"],
        ];
    }
}
