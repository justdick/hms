<?php

namespace Database\Seeders;

use App\Models\MinorProcedureType;
use App\Models\ProcedureTemplate;
use Illuminate\Database\Seeder;

class ProcedureTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find the Caesarean Section procedure type (OBGY32A)
        $cesareanSection = MinorProcedureType::where('code', 'OBGY32A')->first();

        if (! $cesareanSection) {
            $this->command->warn('Caesarean Section procedure type (OBGY32A) not found. Please ensure procedure types are imported.');

            return;
        }

        // Common suture options as per Requirements 5.7
        $sutureOptions = [
            'CHROMIC 0',
            'CHROMIC 1',
            'CHROMIC 2',
            'VICRYL 0',
            'VICRYL 1',
            'VICRYL 2',
            'NYLON',
            'SILK',
        ];

        // Define all template variables as per Requirements 5.1-5.7
        $variables = [
            [
                'key' => 'incision_type',
                'label' => 'Incision Type',
                'options' => ['PFANNENSTIEL', 'MIDLINE'],
            ],
            [
                'key' => 'bladder_flap',
                'label' => 'Bladder Flap',
                'options' => ['was created', 'was not created'],
            ],
            [
                'key' => 'delivery_method',
                'label' => 'Delivery Method',
                'options' => [
                    'baby was cephalic and',
                    'baby was breech and',
                    'baby was transverse and',
                    'first twin was cephalic and',
                    'second twin was cephalic and',
                ],
            ],
            [
                'key' => 'placenta_removal',
                'label' => 'Placenta Removal',
                'options' => [
                    'spontaneously',
                    'by controlled cord traction',
                    'manually',
                ],
            ],
            [
                'key' => 'uterine_layers',
                'label' => 'Uterine Layers',
                'options' => ['ONE LAYER', 'TWO LAYERS'],
            ],
            [
                'key' => 'uterine_suture',
                'label' => 'Uterine Suture',
                'options' => $sutureOptions,
            ],
            [
                'key' => 'fascia_suture',
                'label' => 'Fascia Suture',
                'options' => $sutureOptions,
            ],
            [
                'key' => 'subcutaneous_suture',
                'label' => 'Subcutaneous Suture',
                'options' => $sutureOptions,
            ],
            [
                'key' => 'skin_suture',
                'label' => 'Skin Suture',
                'options' => $sutureOptions,
            ],
        ];

        // C-Section template text with {{variable}} placeholders
        $templateText = <<<'TEMPLATE'
The patient was prepped and draped in the usual sterile fashion in the dorsal supine position with a left-ward tilt. A {{incision_type}} skin incision was made with the scalpel and carried through to the underlying layer of fascia. The fascia was incised and extended laterally. The superior and inferior aspects of the fascial incision was elevated and the underlying rectus muscles were dissected off bluntly in the midline. The peritoneum was bluntly dissected, entered, and extended superiorly and inferiorly with good visualization of the bladder. A bladder flap {{bladder_flap}}. The lower uterine segment was incised in a transverse fashion using the scalpel and extended using manual traction. The {{delivery_method}} subsequently delivered atraumatically. The nose and mouth were bulb suctioned. The cord was clamped and cut. The baby was subsequently handed to the awaiting midwife. The placenta was removed {{placenta_removal}}. The uterus was cleared of all clots and debris. The uterine incision was repaired in {{uterine_layers}} using {{uterine_suture}} suture. The uterine incision was reexamined and was noted to be hemostatic. The rectus muscles were reapproximated in the midline. The fascia was closed with {{fascia_suture}}, the subcutaneous layer was closed with {{subcutaneous_suture}}, and the skin was closed with {{skin_suture}}. Sponge and instrument counts were correct twice.
TEMPLATE;

        // Create or update the C-Section template
        ProcedureTemplate::updateOrCreate(
            [
                'minor_procedure_type_id' => $cesareanSection->id,
            ],
            [
                'procedure_code' => 'OBGY32A',
                'name' => 'Caesarean Section Template',
                'template_text' => $templateText,
                'variables' => $variables,
                'is_active' => true,
            ]
        );

        $this->command->info('C-Section procedure template seeded successfully.');
    }
}
