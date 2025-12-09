<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBackupSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('backups.manage-settings') || $this->user()?->hasRole('Admin');
    }

    public function rules(): array
    {
        return [
            // Schedule settings
            'schedule_enabled' => ['boolean'],
            'schedule_frequency' => ['nullable', 'in:daily,weekly,custom'],
            'schedule_time' => ['nullable', 'date_format:H:i'],
            'cron_expression' => ['nullable', 'string', 'max:100'],

            // Retention settings
            'retention_daily' => ['integer', 'min:0', 'max:365'],
            'retention_weekly' => ['integer', 'min:0', 'max:52'],
            'retention_monthly' => ['integer', 'min:0', 'max:24'],

            // Google Drive settings
            'google_drive_enabled' => ['boolean'],
            'google_drive_folder_id' => ['nullable', 'string', 'max:255'],
            'google_credentials' => ['nullable', 'string'],

            // Notification settings
            'notification_emails' => ['nullable', 'array'],
            'notification_emails.*' => ['email'],
        ];
    }

    public function messages(): array
    {
        return [
            'schedule_frequency.in' => 'Schedule frequency must be daily, weekly, or custom.',
            'schedule_time.date_format' => 'Schedule time must be in HH:MM format.',
            'retention_daily.min' => 'Daily retention must be at least 0.',
            'retention_daily.max' => 'Daily retention cannot exceed 365 days.',
            'retention_weekly.min' => 'Weekly retention must be at least 0.',
            'retention_weekly.max' => 'Weekly retention cannot exceed 52 weeks.',
            'retention_monthly.min' => 'Monthly retention must be at least 0.',
            'retention_monthly.max' => 'Monthly retention cannot exceed 24 months.',
            'notification_emails.*.email' => 'Each notification email must be a valid email address.',
        ];
    }
}
