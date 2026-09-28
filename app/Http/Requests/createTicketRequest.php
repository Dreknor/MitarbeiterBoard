<?php

namespace App\Http\Requests;

use App\Models\TicketCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class createTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('view tickets');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:65000',
            // Kategorie ist Pflicht, sobald Kategorien existieren
            'category_id' => [
                Rule::requiredIf(fn () => TicketCategory::query()->exists()),
                'nullable',
                'integer',
                'exists:ticket_categories,id',
            ],
            'priority' => 'required|in:low,medium,high',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:20480',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Titel',
            'description' => 'Beschreibung',
            'category_id' => 'Kategorie',
            'priority' => 'Priorität',
            'files.*' => 'Datei',
        ];
    }
}
