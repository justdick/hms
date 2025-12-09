<?php

namespace Database\Factories;

use App\Models\BackupSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BackupSettings>
 */
class BackupSettingsFactory extends Factory
{
    protected $model = BackupSettings::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schedule_enabled' => false,
            'schedule_frequency' => 'daily',
            'schedule_time' => '02:00:00',
            'cron_expression' => null,
            'retention_daily' => 7,
            'retention_weekly' => 4,
            'retention_monthly' => 3,
            'google_drive_enabled' => false,
            'google_drive_folder_id' => null,
            'google_credentials' => null,
            'notification_emails' => null,
        ];
    }

    /**
     * Indicate that scheduling is enabled.
     */
    public function withScheduleEnabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_enabled' => true,
        ]);
    }

    /**
     * Indicate weekly schedule frequency.
     */
    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_frequency' => 'weekly',
        ]);
    }

    /**
     * Indicate custom cron schedule.
     */
    public function customCron(string $expression = '0 3 * * *'): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_frequency' => 'custom',
            'cron_expression' => $expression,
        ]);
    }

    /**
     * Indicate that Google Drive is enabled.
     */
    public function withGoogleDrive(): static
    {
        return $this->state(fn (array $attributes) => [
            'google_drive_enabled' => true,
            'google_drive_folder_id' => fake()->uuid(),
        ]);
    }

    /**
     * Set notification emails.
     */
    public function withNotificationEmails(?array $emails = null): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_emails' => $emails ?? [fake()->email(), fake()->email()],
        ]);
    }
}
