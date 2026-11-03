<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Anlegen/Bearbeiten von Meetings über die gruppenunabhängige Meeting-Verwaltung.
 */
class SaveMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'group_id' => $this->input('group_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        $dateRules = ['required', 'date'];
        if ($this->isMethod('post')) {
            $dateRules[] = 'after_or_equal:today';
        }

        return [
            'title'        => ['required', 'string', 'max:255'],
            'date'         => $dateRules,
            'start_time'   => ['required', 'date_format:H:i'],
            'end_time'     => ['required', 'date_format:H:i', 'after:start_time'],
            'description'  => ['nullable', 'string', 'max:5000'],
            'location'     => ['nullable', 'string', 'max:255'],
            'meeting_url'  => ['nullable', 'url', 'max:500'],
            'group_id'     => ['nullable', 'integer', Rule::exists('groups', 'id')->whereNull('deleted_at')],

            'users'        => ['nullable', 'array'],
            'users.*'      => ['integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'organizers'   => ['nullable', 'array'],
            'organizers.*' => ['integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'groups'       => ['nullable', 'array'],
            'groups.*'     => ['integer', Rule::exists('groups', 'id')->whereNull('deleted_at')],
            'roles'        => ['nullable', 'array'],
            'roles.*'      => ['integer', Rule::exists('roles', 'id')],

            'book_room'    => ['nullable', 'boolean'],
            'room_id'      => [
                'nullable',
                'required_if:book_room,1',
                Rule::exists('rooms', 'id')->where(fn ($query) => $query->where('bookable', true)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'end_time.after'       => 'Die Endzeit muss nach der Startzeit liegen.',
            'meeting_url.url'      => 'Bitte einen gültigen Link (https://…) angeben.',
            'room_id.required_if'  => 'Bitte einen buchbaren Raum auswählen.',
            'room_id.exists'       => 'Der ausgewählte Raum ist nicht buchbar oder nicht vorhanden.',
            'date.after_or_equal'  => 'Das Datum darf nicht in der Vergangenheit liegen.',
        ];
    }

    /**
     * Teilnehmer-Daten für MeetingService::syncParticipants().
     */
    public function participantData(): array
    {
        return [
            'users'      => $this->input('users', []),
            'organizers' => $this->input('organizers', []),
            'groups'     => $this->input('groups', []),
            'roles'      => $this->input('roles', []),
        ];
    }
}
