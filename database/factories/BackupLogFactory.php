<?php

namespace Database\Factories;

use App\Models\Backup;
use App\Models\BackupLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BackupLog>
 */
class BackupLogFactory extends Factory
{
    protected $model = BackupLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'backup_id' => Backup::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['created', 'deleted', 'restored', 'downloaded', 'settings_changed', 'retention_cleanup']),
            'details' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the action is a backup creation.
     */
    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'created',
            'details' => 'Backup created successfully',
        ]);
    }

    /**
     * Indicate that the action is a backup deletion.
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'deleted',
            'details' => 'Backup deleted',
        ]);
    }

    /**
     * Indicate that the action is a restore.
     */
    public function restored(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'restored',
            'details' => 'Database restored from backup',
        ]);
    }

    /**
     * Indicate that the action is a download.
     */
    public function downloaded(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'downloaded',
            'details' => 'Backup downloaded',
        ]);
    }

    /**
     * Indicate that the action is a settings change.
     */
    public function settingsChanged(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'settings_changed',
            'backup_id' => null,
            'details' => 'Backup settings updated',
        ]);
    }

    /**
     * Indicate that the action is a retention cleanup.
     */
    public function retentionCleanup(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'retention_cleanup',
            'details' => 'Backup deleted by retention policy',
        ]);
    }
}
