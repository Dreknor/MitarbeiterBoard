<?php

namespace App\Http\Requests\API\v1;

use App\Models\GradingDocumentationSession;
use App\Models\PaedDiaryClassGroup;
use App\Models\Schueler;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /grading/sessions
 * - bisher: {schueler_id} → individuelle Session (unverändert)
 * - neu:    {type: "group", class_id, schueler_ids?, answer_order_mode?, group_id?} → Gruppensession
 */
class StoreGradingSessionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'in:individual,group'],
            'schueler_id' => ['exclude_if:type,group', 'required', 'integer', $this->existingSchueler()],

            'class_id' => ['exclude_unless:type,group', 'required', 'integer', 'exists:klassen,id'],
            // Ohne Angabe: alle Schüler der Klasse (wie im Web)
            'schueler_ids' => ['exclude_unless:type,group', 'sometimes', 'array', 'min:1', 'max:200'],
            'schueler_ids.*' => ['integer', 'distinct', $this->existingSchueler()],
            'answer_order_mode' => ['exclude_unless:type,group', 'sometimes', 'nullable', Rule::in(GradingDocumentationSession::ANSWER_ORDER_MODES)],
            // Optional: eigene Lerngruppe (wie im Web), muss die Klasse enthalten
            'group_id' => ['exclude_unless:type,group', 'sometimes', 'nullable', 'integer', 'exists:paed_diary_class_groups,id'],
        ];
    }

    public function isGroup(): bool
    {
        return $this->input('type') === 'group';
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty() || !$this->isGroup()) {
                return;
            }

            $classId = (int) $this->input('class_id');

            // Eine Session gehört genau zu einer Klasse (grading_documentation_sessions.klasse_id)
            $ids = collect($this->input('schueler_ids', []))->map(fn ($id) => (int) $id);
            if ($ids->isNotEmpty()) {
                $foreign = $ids->diff(Schueler::whereIn('id', $ids)->where('klasse_id', $classId)->pluck('id'));
                if ($foreign->isNotEmpty()) {
                    $v->errors()->add('schueler_ids', 'Alle Schüler müssen der Klasse der Session angehören (nicht zutreffend: ' . $foreign->implode(', ') . ').');
                }
            }

            if ($this->filled('group_id')) {
                $group = PaedDiaryClassGroup::with('klassen:id')->find($this->integer('group_id'));
                if (!$group || (int) $group->user_id !== (int) $this->user()?->id || !$group->klassen->contains('id', $classId)) {
                    $v->errors()->add('group_id', 'Die Lerngruppe gehört nicht zum Benutzer oder enthält die Klasse nicht.');
                }
            }
        });
    }
}
