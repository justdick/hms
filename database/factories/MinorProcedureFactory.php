<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MinorProcedure>
 */
class MinorProcedureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Get a random procedure type from the database
        $procedureType = \App\Models\MinorProcedureType::inRandomOrder()->first();

        // If no procedure types exist, create a default one
        if (! $procedureType) {
            $procedureType = \App\Models\MinorProcedureType::create([
                'name' => 'Wound Dressing',
                'code' => 'WD001',
                'category' => 'wound_care',
                'description' => 'Cleaning and dressing of wounds',
                'price' => 50.00,
                'is_active' => true,
            ]);
        }

        $procedureNotes = [
            'Wound Dressing' => 'Cleaned wound with normal saline, applied antiseptic (Betadine), dressed with sterile gauze. Wound healing well, no signs of infection. Patient advised to keep area dry.',
            'Catheter Change (Urinary)' => 'Removed old catheter, cleaned urethral area with antiseptic solution. Inserted new Foley catheter (size 16Fr) using sterile technique. Balloon inflated with 10ml sterile water. Drainage bag attached. Patient tolerated procedure well.',
            'Catheter Change (IV)' => 'Removed old IV cannula from right hand. Site clean, no signs of phlebitis. Inserted new 20G cannula in left forearm using aseptic technique. Secured with transparent dressing. IV fluids running well.',
            'Suture Removal' => 'Removed 8 sutures from surgical wound. Wound well healed, edges approximated. No discharge or signs of infection. Steri-strips applied for additional support. Patient advised on wound care.',
            'Dressing Change' => 'Removed old dressing. Wound inspected - clean, dry, healing well. Cleaned with normal saline, applied new sterile dressing. No signs of infection or complications noted.',
            'Injection (IM)' => 'Administered intramuscular injection in right deltoid muscle using aseptic technique. Patient tolerated procedure well. Advised to report any adverse reactions.',
            'Injection (IV)' => 'Administered intravenous injection via existing IV line. Flushed line before and after administration. No adverse reactions observed. Patient monitored for 15 minutes post-injection.',
            'Injection (SC)' => 'Administered subcutaneous injection in abdomen using aseptic technique. Patient tolerated procedure well. Injection site clean, no bleeding or swelling.',
            'Nebulization' => 'Administered nebulization treatment with prescribed medication. Patient positioned comfortably in sitting position. Treatment completed over 15 minutes. Respiratory rate and oxygen saturation monitored. Patient breathing easier post-treatment.',
        ];

        return [
            'patient_checkin_id' => \App\Models\PatientCheckin::factory(),
            'nurse_id' => \App\Models\User::factory(),
            'minor_procedure_type_id' => $procedureType->id,
            'procedure_type' => $procedureType->name, // Keep for backward compatibility
            'procedure_notes' => $procedureNotes[$procedureType->name] ?? $this->faker->paragraph(3),
            'performed_at' => $this->faker->dateTimeBetween('-2 hours', 'now'),
            'status' => $this->faker->randomElement(['in_progress', 'completed']),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    public function woundDressing(): static
    {
        return $this->state(fn (array $attributes) => [
            'procedure_type' => 'Wound Dressing',
            'procedure_notes' => 'Cleaned wound with normal saline, applied antiseptic (Betadine), dressed with sterile gauze. Wound healing well, no signs of infection. Patient advised to keep area dry.',
        ]);
    }

    public function catheterChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'procedure_type' => 'Catheter Change (Urinary)',
            'procedure_notes' => 'Removed old catheter, cleaned urethral area with antiseptic solution. Inserted new Foley catheter (size 16Fr) using sterile technique. Balloon inflated with 10ml sterile water. Drainage bag attached. Patient tolerated procedure well.',
        ]);
    }

    public function sutureRemoval(): static
    {
        return $this->state(fn (array $attributes) => [
            'procedure_type' => 'Suture Removal',
            'procedure_notes' => 'Removed 8 sutures from surgical wound. Wound well healed, edges approximated. No discharge or signs of infection. Steri-strips applied for additional support. Patient advised on wound care.',
        ]);
    }

    public function injection(): static
    {
        return $this->state(fn (array $attributes) => [
            'procedure_type' => $this->faker->randomElement(['Injection (IM)', 'Injection (IV)', 'Injection (SC)']),
        ]);
    }

    public function nebulization(): static
    {
        return $this->state(fn (array $attributes) => [
            'procedure_type' => 'Nebulization',
            'procedure_notes' => 'Administered nebulization treatment with prescribed medication. Patient positioned comfortably in sitting position. Treatment completed over 15 minutes. Respiratory rate and oxygen saturation monitored. Patient breathing easier post-treatment.',
        ]);
    }
}
