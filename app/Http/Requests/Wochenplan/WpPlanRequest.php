<?php

namespace App\Http\Requests\Wochenplan;

use App\Models\Wochenplan\WpPlan;
use App\Policies\WpPlanPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Anlegen und Ändern eines Plans. Neue Klassenpläne brauchen eine Klasse,
 * und zwar eine, auf die die Person Zugriff hat. Rechte am Plan prüft der Controller (Policy).
 */
class WpPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create wochenplan');
    }

    protected function prepareForValidation(): void
    {
        foreach (['taegliche_uebungen_aktiv', 'is_vorlage'] as $feld) {
            if ($this->has($feld)) {
                $this->merge([$feld => filter_var($this->input($feld), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    public function rules(): array
    {
        /** @var WpPlan|null $plan */
        $plan = $this->route('wpPlan');
        $neu = $plan === null;
        $vorlage = $neu ? $this->boolean('is_vorlage') : $plan->isVorlage();
        // Neue Klassenpläne brauchen eine Klasse; bestehende Klassenpläne können sie nicht mehr verlieren.
        $klasseNoetig = ! $vorlage && ($neu || ($plan->isKlassenplan() && $plan->klasse_id !== null && $this->has('klasse_id')));

        return [
            'name' => [$neu || $this->has('name') ? 'required' : 'sometimes', 'string', 'max:255'],
            'gueltig_von' => [$neu ? 'required' : 'sometimes', 'date'],
            'gueltig_bis' => [$neu ? 'required' : 'sometimes', 'date', 'after_or_equal:gueltig_von'],
            'klasse_id' => [$klasseNoetig ? 'required' : 'nullable', 'integer', 'exists:klassen,id'],
            'selbsteinschaetzung' => ['sometimes', 'integer', 'in:0,1,2'],
            'niveau' => ['nullable', 'integer', 'in:1,2,3'],
            'taegliche_uebungen_aktiv' => ['sometimes', 'boolean'],
            'formatvorlage_id' => ['nullable', 'integer', 'exists:wp_formatvorlagen,id'],
            'is_vorlage' => ['sometimes', 'boolean'],
            'vorlage_name' => ['nullable', 'required_if:is_vorlage,true', 'string', 'max:255'],
            'vorlage_id' => ['nullable', 'integer', 'exists:wp_plaene,id'],
            'version' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'klasse_id.required' => 'Bitte eine Klasse auswählen.',
            'gueltig_bis.after_or_equal' => '„Gültig bis“ darf nicht vor „Gültig von“ liegen.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->filled('klasse_id') && ! WpPlanPolicy::darfKlasse($this->user(), (int) $this->input('klasse_id'))) {
                    $validator->errors()->add('klasse_id', 'Für diese Klasse haben Sie keinen Zugriff.');
                }
            },
        ];
    }
}
