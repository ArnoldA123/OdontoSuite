<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Slice 03 (T-03.7): status transitions validated against the
 * pending -> queued -> sent|failed state machine.
 */
class UpdateReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['sometimes', 'date'],
            'sent_at' => ['sometimes', 'nullable', 'date'],
            'channel' => ['sometimes', 'string', 'in:sms,email,whatsapp,push'],
            'status' => ['sometimes', 'string', 'in:pending,queued,sent,failed,cancelled'],
            'error_message' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
