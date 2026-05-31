<?php

namespace App\Http\Requests\Procedure;

use App\Models\Procedure_Step;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validierung beim Erstellen eines Kommentars (§8.3).
 *
 * Berechtigung: User muss dem Schritt zugewiesen sein ODER `manage procedures`
 * besitzen UND zusätzlich `comment procedure steps` haben (außer manage).
 */
class StoreProcedureStepCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) return false;

        /** @var Procedure_Step $step */
        $step = $this->route('step');
        if (!$step instanceof Procedure_Step) return false;

        if ($user->can('manage procedures')) return true;
        if (!$user->can('comment procedure steps')) return false;
        return $step->users()->where('users.id', $user->id)->exists();
    }

    public function rules(): array
    {
        return [
            'body' => 'required|string|max:4000',
        ];
    }
}

