<?php

namespace App\Http\Requests\API\v1;

use Illuminate\Validation\Validator;

class StoreGradingAssessmentRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            // Bei individuellen Sessions optional (Default: Schüler der Session), bei Gruppen-Sessions Pflicht
            'schueler_id' => ['sometimes', 'integer', $this->existingSchueler()],
            'answers' => ['sometimes', 'array', 'max:200'],
            'answers.*.question_id' => ['required', 'integer', 'distinct', 'exists:grading_questions,id'],
            // Pädagogenbewertung (1–5)
            'answers.*.rating_value' => ['nullable', 'integer', 'min:1', 'max:5'],
            // Optionale Selbsteinschätzung des Schülers (1–5)
            'answers.*.self_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'answers.*.comment' => ['nullable', 'string', 'max:5000'],
            'teacher_assessment' => ['nullable', 'string', 'max:5000'],
            'finalize' => ['sometimes', 'boolean'],
            // Nur beim Abschluss: neue Graduierungsstufe vergeben (erfordert "manage grading systems")
            'grading_stage_id' => ['sometimes', 'nullable', 'integer', 'exists:grading_stages,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (!$this->filled('answers') && !$this->filled('teacher_assessment') && !$this->boolean('finalize')) {
                $v->errors()->add('answers', 'Es muss mindestens eine Antwort, eine Pädagogenbewertung oder "finalize" übermittelt werden.');
            }
            if ($this->filled('grading_stage_id') && !$this->boolean('finalize')) {
                $v->errors()->add('grading_stage_id', 'Eine Stufe kann nur beim Abschluss (finalize = true) vergeben werden.');
            }
        });
    }
}
