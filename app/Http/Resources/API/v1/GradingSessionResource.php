<?php

namespace App\Http\Resources\API\v1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Graduierungs-Session inkl. Fragenkatalog und Zwischenstand.
 *
 * @mixin \App\Models\GradingDocumentationSession
 */
class GradingSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'answer_order_mode' => $this->answer_order_mode,
            'class_id' => $this->klasse_id,
            'schueler_id' => $this->schueler_id,
            'grading_system' => $this->whenLoaded('gradingSystem', fn () => [
                'id' => $this->gradingSystem->id,
                'name' => $this->gradingSystem->name,
            ]),
            'created_by_id' => $this->user_id,
            'created_by_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'is_owner' => $user ? (int) $this->user_id === (int) $user->id : false,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_completed' => $this->completed_at !== null,
            'questions' => $this->whenLoaded('gradingSystem', function () {
                if (!$this->gradingSystem->relationLoaded('questions')) {
                    return [];
                }

                return $this->gradingSystem->questions->map(fn ($q) => [
                    'id' => $q->id,
                    'question' => $q->question,
                    'sort_order' => (int) $q->sort_order,
                ])->values();
            }),
            'answers' => $this->whenLoaded('teacherAssessments', function () {
                $selfRatings = $this->relationLoaded('studentAnswers')
                    ? $this->studentAnswers->keyBy(fn ($a) => $a->schueler_id . '-' . $a->question_id)
                    : collect();

                $keys = $this->teacherAssessments->map(fn ($a) => $a->schueler_id . '-' . $a->question_id)
                    ->merge($selfRatings->keys())->unique();

                $teacher = $this->teacherAssessments->keyBy(fn ($a) => $a->schueler_id . '-' . $a->question_id);

                return $keys->map(function ($key) use ($teacher, $selfRatings) {
                    [$schuelerId, $questionId] = array_map('intval', explode('-', $key));
                    $t = $teacher->get($key);
                    $s = $selfRatings->get($key);

                    return [
                        'schueler_id' => $schuelerId,
                        'question_id' => $questionId,
                        'rating_value' => $t?->teacher_rating !== null ? (int) $t->teacher_rating : null,
                        'self_rating' => $s?->self_rating !== null ? (int) $s->self_rating : null,
                        'comment' => $t?->comment,
                        'assessed_at' => $t?->assessed_at?->toIso8601String(),
                    ];
                })->values();
            }),
            'teacher_assessments' => $this->whenLoaded('coachingNotes', fn () => $this->coachingNotes->map(fn ($n) => [
                'schueler_id' => (int) $n->schueler_id,
                'note' => $n->note,
                'noted_at' => $n->noted_at?->toIso8601String(),
            ])->values()),
        ];
    }
}
