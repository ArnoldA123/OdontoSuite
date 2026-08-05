<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Slice 03 (T-03.6): channel whitelist restricted to [sms,email,whatsapp,push].
 * Unknown channel -> 422 (validated automatically by Laravel).
 */
class StoreReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'reminder_template_id' => ['nullable', 'integer', 'exists:reminder_templates,id'],
            'scheduled_at' => ['required', 'date'],
            'channel' => ['required', 'string', 'in:sms,email,whatsapp,push'],
            'status' => ['sometimes', 'string', 'in:pending,queued'],
            'anticipation_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
        ];
    }

    public function messages(): array
    {
        return [
            'channel.in' => 'El canal debe ser uno de: sms, email, whatsapp, push.',
        ];
    }
}
