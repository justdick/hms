<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class VitalsScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $activeAdmissions = \App\Models\PatientAdmission::where('status', 'admitted')->get();

        if ($activeAdmissions->isEmpty()) {
            $this->command->info('No active admissions found. Skipping vitals schedule seeding.');

            return;
        }

        $users = \App\Models\User::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Creating a default user for seeding.');
            $users = collect([\App\Models\User::factory()->create()]);
        }

        $intervalOptions = [60, 120, 240, 360, 480, 720];

        foreach ($activeAdmissions->take(10) as $admission) {
            $intervalMinutes = $this->command->choice(
                "Select interval for {$admission->patient->name} (or press enter for random)",
                array_merge(['random'], array_map(fn ($m) => "{$m} minutes", $intervalOptions)),
                0
            );

            if ($intervalMinutes === 'random') {
                $intervalMinutes = fake()->randomElement($intervalOptions);
            } else {
                $intervalMinutes = (int) str_replace(' minutes', '', $intervalMinutes);
            }

            $lastRecordedAt = now()->subMinutes($intervalMinutes + fake()->numberBetween(-30, 30));

            \App\Models\VitalsSchedule::create([
                'patient_admission_id' => $admission->id,
                'interval_minutes' => $intervalMinutes,
                'next_due_at' => $lastRecordedAt->copy()->addMinutes($intervalMinutes),
                'last_recorded_at' => $lastRecordedAt,
                'is_active' => true,
                'created_by' => $users->random()->id,
            ]);

            $this->command->info("Created vitals schedule for {$admission->patient->name} with {$intervalMinutes} minute interval");
        }

        $this->command->info('Vitals schedules seeded successfully!');
    }
}
