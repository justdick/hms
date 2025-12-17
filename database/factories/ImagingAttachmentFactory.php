<?php

namespace Database\Factories;

use App\Models\ImagingAttachment;
use App\Models\LabOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImagingAttachment>
 */
class ImagingAttachmentFactory extends Factory
{
    protected $model = ImagingAttachment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fileTypes = [
            ['type' => 'image/jpeg', 'ext' => 'jpg'],
            ['type' => 'image/png', 'ext' => 'png'],
            ['type' => 'application/pdf', 'ext' => 'pdf'],
        ];

        $fileType = $this->faker->randomElement($fileTypes);
        $fileName = $this->faker->uuid().'.'.$fileType['ext'];

        return [
            'lab_order_id' => LabOrder::factory(),
            'file_path' => 'imaging/'.date('Y/m').'/'.$fileName,
            'file_name' => $fileName,
            'file_type' => $fileType['type'],
            'file_size' => $this->faker->numberBetween(100000, 50000000), // 100KB to 50MB
            'description' => $this->faker->optional(0.7)->randomElement([
                'PA View',
                'Lateral View',
                'AP View',
                'Oblique View',
                'Axial View',
                'Coronal View',
                'Sagittal View',
            ]),
            'is_external' => false,
            'external_facility_name' => null,
            'external_study_date' => null,
            'uploaded_by' => User::factory(),
            'uploaded_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the attachment is from an external facility.
     */
    public function external(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_external' => true,
            'external_facility_name' => $this->faker->company().' Hospital',
            'external_study_date' => $this->faker->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }

    /**
     * Indicate that the attachment is a JPEG image.
     */
    public function jpeg(): static
    {
        $fileName = $this->faker->uuid().'.jpg';

        return $this->state(fn (array $attributes) => [
            'file_path' => 'imaging/'.date('Y/m').'/'.$fileName,
            'file_name' => $fileName,
            'file_type' => 'image/jpeg',
        ]);
    }

    /**
     * Indicate that the attachment is a PNG image.
     */
    public function png(): static
    {
        $fileName = $this->faker->uuid().'.png';

        return $this->state(fn (array $attributes) => [
            'file_path' => 'imaging/'.date('Y/m').'/'.$fileName,
            'file_name' => $fileName,
            'file_type' => 'image/png',
        ]);
    }

    /**
     * Indicate that the attachment is a PDF document.
     */
    public function pdf(): static
    {
        $fileName = $this->faker->uuid().'.pdf';

        return $this->state(fn (array $attributes) => [
            'file_path' => 'imaging/'.date('Y/m').'/'.$fileName,
            'file_name' => $fileName,
            'file_type' => 'application/pdf',
        ]);
    }
}
