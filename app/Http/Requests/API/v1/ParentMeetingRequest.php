<?php

namespace App\Http\Requests\API\v1;

use App\Http\Requests\PaedDiaryElterngespraechRequest;

/**
 * POST /students/{id}/parent-meetings und PUT /parent-meetings/{id} – gleiche Regeln wie im Web.
 */
class ParentMeetingRequest extends ApiRequest
{
    public function rules(): array
    {
        return PaedDiaryElterngespraechRequest::regeln($this->isMethod('post'), $this->filled('start_time'));
    }

    public function messages(): array
    {
        return (new PaedDiaryElterngespraechRequest())->messages();
    }

    /** Validierte Felder ohne Steuerwerte (version). */
    public function daten(): array
    {
        return collect($this->validated())->except('version')->all();
    }

    public function version(): ?int
    {
        return $this->filled('version') ? (int) $this->input('version') : null;
    }
}
