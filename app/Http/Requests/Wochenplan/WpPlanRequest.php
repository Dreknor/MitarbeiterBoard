<?php
namespace App\Http\Requests\Wochenplan;
use Illuminate\Foundation\Http\FormRequest;
class WpPlanRequest extends FormRequest {
    public function authorize(): bool { return auth()->user()->can("create wochenplan"); }
    public function rules(): array {
        return [
            "name"                => ["required", "string", "max:255"],
            "gueltig_von"         => ["required", "date"],
            "gueltig_bis"         => ["required", "date", "after_or_equal:gueltig_von"],
            "klasse_id"           => ["nullable", "exists:klassen,id"],
            "schueler_id"         => ["nullable", "exists:schueler,id"],
            "selbsteinschaetzung" => ["required", "integer", "in:0,1,2"],
            "formatvorlage_id"    => ["nullable", "exists:wp_formatvorlagen,id"],
            "is_vorlage"          => ["sometimes", "boolean"],
            "vorlage_name"        => ["nullable", "required_if:is_vorlage,true", "string", "max:255"],
        ];
    }
}
