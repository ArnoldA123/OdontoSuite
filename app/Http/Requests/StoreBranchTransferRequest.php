<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #305 (S2/S4) — the self-service door that opens a transfer request.
 *
 * The destination must be an active sede and must differ from the one the user
 * already has: asking to stay where you are is not a transfer. A user with
 * group scope (`branch_id` null) has no current sede to differ from, so any
 * active destination is a move for them (D1).
 *
 * The rest of the rules (one open request, origin still matching) are state
 * conflicts of `BranchTransferService`, not payload shape.
 */
class StoreBranchTransferRequest extends FormRequest
{
    use LocalizedErrors;

    public function authorize(): bool
    {
        // Authorization is the route's `permission:` middleware; this request
        // only owns the shape of the payload.
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $currentBranchId = $this->user()?->branch_id;

        $destination = [
            'required',
            'integer',
            Rule::exists('branches', 'id')->where('is_active', true),
        ];

        if ($currentBranchId !== null) {
            $destination[] = Rule::notIn([(int) $currentBranchId]);
        }

        return [
            'to_branch_id' => $destination,
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_branch_id.required' => 'La sede de destino es requerida.',
            'to_branch_id.integer' => 'La sede de destino debe ser un identificador válido.',
            'to_branch_id.exists' => 'La sede de destino no existe o no está activa.',
            'to_branch_id.not_in' => 'La sede de destino debe ser distinta de su sede actual.',
            'reason.required' => 'El motivo del traslado es requerido.',
            'reason.string' => 'El motivo del traslado debe ser texto.',
            'reason.max' => 'El motivo del traslado no puede exceder 500 caracteres.',
        ];
    }
}
