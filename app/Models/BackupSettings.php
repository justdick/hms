<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupSettings extends Model
{
    /** @use HasFactory<\Database\Factories\BackupSettingsFactory> */
    use HasFactory;

    protected $fillable = [
        'schedule_enabled',
        'schedule_frequency',
        'schedule_time',
        'cron_expression',
        'retention_daily',
        'retention_weekly',
        'retention_monthly',
        'google_drive_enabled',
        'google_drive_folder_id',
        'google_credentials',
        'notification_emails',
    ];

    protected function casts(): array
    {
        return [
            'schedule_enabled' => 'boolean',
            'google_drive_enabled' => 'boolean',
            'notification_emails' => 'array',
            'retention_daily' => 'integer',
            'retention_weekly' => 'integer',
            'retention_monthly' => 'integer',
            'google_credentials' => 'encrypted',
        ];
    }

    /**
     * Get the singleton instance of backup settings.
     * Creates a default record if none exists.
     */
    public static function getInstance(): self
    {
        $settings = self::first();

        if (! $settings) {
            $settings = self::create([
                'schedule_enabled' => false,
                'schedule_frequency' => 'daily',
                'schedule_time' => '02:00:00',
                'retention_daily' => 7,
                'retention_weekly' => 4,
                'retention_monthly' => 3,
                'google_drive_enabled' => false,
            ]);
        }

        return $settings;
    }
}
