<?php

namespace Database\Factories;

use App\Models\Backup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Backup>
 */
class BackupFactory extends Factory
{
    protected $model = Backup::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timestamp = fake()->dateTimeBetween('-30 days', 'now');
        $filename = 'hms_backup_'.$timestamp->format('Ymd_His').'.sql.gz';

        return [
            'filename' => $filename,
            'file_size' => fake()->numberBetween(1000000, 500000000), // 1MB to 500MB
            'file_path' => 'backups/'.$filename,
            'google_drive_file_id' => null,
            'status' => 'completed',
            'source' => fake()->randomElement(['manual_ui', 'manual_cli', 'scheduled']),
            'is_protected' => false,
            'created_by' => User::factory(),
            'completed_at' => $timestamp,
            'error_message' => null,
        ];
    }

    /**
     * Indicate that the backup is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the backup is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the backup failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'completed_at' => null,
            'error_message' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the backup is protected.
     */
    public function protected(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_protected' => true,
        ]);
    }

    /**
     * Indicate that the backup was created from UI.
     */
    public function fromUi(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'manual_ui',
        ]);
    }

    /**
     * Indicate that the backup was created from CLI.
     */
    public function fromCli(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'manual_cli',
        ]);
    }

    /**
     * Indicate that the backup was scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'scheduled',
        ]);
    }

    /**
     * Indicate that the backup is a pre-restore backup.
     */
    public function preRestore(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'pre_restore',
        ]);
    }

    /**
     * Indicate that the backup is on Google Drive.
     */
    public function onGoogleDrive(): static
    {
        return $this->state(fn (array $attributes) => [
            'google_drive_file_id' => fake()->uuid(),
        ]);
    }

    /**
     * Indicate that the backup is local only (no Google Drive).
     */
    public function localOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'google_drive_file_id' => null,
        ]);
    }
}
