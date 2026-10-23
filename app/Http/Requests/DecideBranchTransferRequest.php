<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use Illuminate\Foundation\Http\FormRequest;

/**
 * #305 (S2/S9) — the two decision doors.
 *
 * The notes are the only payload: who decided and when are the session and the
 * clock, and the state rules live in `BranchTransferService` because they
 * depend on the request and the current affiliation, not on the body.
 */
class DecideBranchTransferRequest extends FormRequest
{
    use LocalizedErrors;

    public function authorize(): bool
    {
        // Authorization is the route's `permission:` + `branch.global`
        // middleware; this request only owns the shape of the payload.
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision_notes.string' => 'Las notas de la decisión deben ser texto.',
            'decision_notes.max' => 'Las notas de la decisión no pueden exceder 1000 caracteres.',
        ];
    }
}
