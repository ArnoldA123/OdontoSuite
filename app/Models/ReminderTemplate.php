<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReminderTemplate extends Model
{
    use HasFactory;

    /**
     * Slice 03 (T-03.4): fillable corrected to match the real
     * reminder_templates table (body_html / body_text, NOT body).
     */
    protected $fillable = [
        'name',
        'type',
        'subject',
        'body_html',
        'body_text',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the reminder schedules for the template.
     */
    public function reminderSchedules(): HasMany
    {
        return $this->hasMany(ReminderSchedule::class);
    }

    /**
     * Scope a query to only include active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include templates of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Render the template with the given variables.
     */
    public function render(array $variables = []): array
    {
        $subject = $this->subject;
        $body = $this->body_text ?? $this->body_html ?? '';

        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $subject = str_replace($placeholder, (string) $value, $subject);
            $body = str_replace($placeholder, (string) $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
